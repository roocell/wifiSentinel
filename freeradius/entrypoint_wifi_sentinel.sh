#!/bin/bash
echo "********************************************************"
echo " MODIFYING MYSQL FREERADIUS CONFIG"
echo "********************************************************"

# pass in mysql_ip arg "-e $(docker inspect --format='{{.NetworkSettings.IPAddress}} mysql)"
echo $mysql_ip 
echo $mysql_port
echo $mysql_radius_db
echo $mysql_login
echo $mysql_password

cp -f /usr/local/etc/raddb/mods-available/sql /usr/local/etc/raddb/mods-enabled
sed -i "s/.*driver =.*/driver = \"rlm_sql_mysql\"/" /usr/local/etc/raddb/mods-enabled/sql
sed -i "s/.*server =.*/server = \"$mysql_ip\"/" /usr/local/etc/raddb/mods-enabled/sql
sed -i "s/.*port =.*/port = $mysql_port/" /usr/local/etc/raddb/mods-enabled/sql
sed -i "s/.*login =.*/login = \"$mysql_login\"/" /usr/local/etc/raddb/mods-enabled/sql
sed -i "s/.*password =.*/password = \"$mysql_password\"/" /usr/local/etc/raddb/mods-enabled/sql
sed -i "s/.*radius_db =.*/radius_db = \"$mysql_radius_db\"/" /usr/local/etc/raddb/mods-enabled/sql

bash 
#radiusd -f -xx

