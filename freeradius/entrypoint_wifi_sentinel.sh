#!/bin/bash
echo "********************************************************"
echo " modifying MYSQL freeradius config"
echo "********************************************************"

# pass in mysql_ip arg "-e $(docker inspect --format='{{.NetworkSettings.IPAddress}} mysql)"
echo $MYSQL_IP
echo $MYSQL_PORT
echo $MYSQL_LOGIN
echo $MYSQL_PASSWORD
echo $MYSQL_RADIUS_DB

cp -f /usr/local/etc/raddb/mods-available/sql /usr/local/etc/raddb/mods-enabled/
sed -i "s/.*driver =.*/driver = \"rlm_sql_mysql\"/" /usr/local/etc/raddb/mods-enabled/sql
sed -i "s/.*server =.*/server = \"$MYSQL_IP\"/" /usr/local/etc/raddb/mods-enabled/sql
sed -i "s/.*port =.*/port = $MYSQL_PORT/" /usr/local/etc/raddb/mods-enabled/sql
sed -i "s/.*login =.*/login = \"$MYSQL_LOGIN\"/" /usr/local/etc/raddb/mods-enabled/sql
sed -i "s/.*password =.*/password = \"$MYSQL_PASSWORD\"/" /usr/local/etc/raddb/mods-enabled/sql
sed -i "s/.*radius_db =.*/radius_db = \"$MYSQL_RADIUS_DB\"/" /usr/local/etc/raddb/mods-enabled/sql

# replace first instance of '-sql' : this is the authorize section
sed -i '0,/-sql/s/-sql/sql/' /usr/local/etc/raddb/sites-enabled/default
sed -i '0,/-sql/s/-sql/sql/' /usr/local/etc/raddb/sites-enabled/inner-tunnel

# enable Seesion-Timeout in Access-Accept
line1=$(grep -n "update {" /usr/local/etc/raddb/sites-enabled/inner-tunnel | cut -d : -f 1)
line2=$(tail -n +$line1 /usr/local/etc/raddb/sites-enabled/inner-tunnel | grep -n -m1 "}" | cut -d : -f 1)
line3=$(($line1+$line2))
line4=$(grep -n -m 1 "update outer.session-state {" /usr/local/etc/raddb/sites-enabled/inner-tunnel | cut -d : -f 1)
line5=$(tail -n +$line4 /usr/local/etc/raddb/sites-enabled/inner-tunnel | grep -n -m1 "}" | cut -d : -f 1)
line6=$(($line4+$line5))
echo $line1, $line2, $line3, $line4, $line5, $line6
awk "NR>=$line1 && NR<=$line3 { sub(\"#\", \"\")} {print }" /usr/local/etc/raddb/sites-enabled/inner-tunnel > inner-tunnel.tmp
awk "NR>=$line4 && NR<=$line6 { sub(\"#\", \"\")} {print }" inner-tunnel.tmp > inner-tunnel.tmp2
cp inner-tunnel.tmp2 /usr/local/etc/raddb/sites-enabled/inner-tunnel

echo "********************************************************"
echo " modifying CLIENTS.CONF freeradius config"
echo "********************************************************"
echo $APIP
echo $RADIUS_SECRET

echo "********************************************************"
echo " modifying NOTIFY_AUTH freeradius config"
echo "********************************************************"
echo $NOTIFY_AUTH_IP
echo $NOTIFY_AUTH_PORT
sed -i "s/.*dest_ip =.*/dest_ip = \"$NOTIFY_AUTH_IP\"/" /usr/local/etc/raddb/mods-enabled/notify_auth
sed -i "s/.*dest_port =.*/dest_port = \"$NOTIFY_AUTH_PORT\"/" /usr/local/etc/raddb/mods-enabled/notify_auth
# also have notify_auth hooks in sites-enabled/default, sites-enabled/inner-tunnel
# but they have been checked into github and come as part of the compile

echo -e "client $APIP {\n
       secret          = $RADIUS_SECRET\n
       shortname       = wifi_sentinel-ap\n
}\n" >> /usr/local/etc/raddb/clients.conf

touch /var/log/radius.log
echo "/var/log/radius.log {
    su
    rotate 2
    copytruncate
    size 10K
}" >> /logrotate.conf
fork_logrotate() {
  # must be after radius start (so it's the same FD in the logfile)
   sleep 3
   logrotate  logrotate.conf
}
fork_logrotate &

#bash
exec radiusd -f -xx -l /var/log/radius.log
