#!/bin/bash
set -e # fail on any error

groupadd freerad
useradd -gfreerad freerad

echo '* Working around permission errors locally by making sure that "freerad" uses the same uid and gid as the host volume'
TARGET_UID=$(stat -c "%u" /usr/local/etc/raddb/)
echo '-- Setting freerad user to use uid '$TARGET_UID
usermod -o -u $TARGET_UID freerad || true
TARGET_GID=$(stat -c "%g" /usr/local/etc/raddb/)
echo '-- Setting freerad group to use gid '$TARGET_GID
groupmod -o -g $TARGET_GID freerad || true

chmod 700 /usr/local/etc/raddb 
chmod 700 /usr/local/etc/raddb/clients.conf 
chmod 700 /usr/local/etc/raddb/mods-config/files/authorize
chmod 777 /var/log/freeradius
chmod 666 /var/log/freeradius/radius.log

