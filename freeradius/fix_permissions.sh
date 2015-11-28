#!/bin/bash
set -e # fail on any error

echo '* Working around permission errors locally by making sure that "freerad" uses the same uid and gid as the host volume'
TARGET_UID=$(stat -c "%u" /etc/freeradius)
echo '-- Setting freerad user to use uid '$TARGET_UID
usermod -o -u $TARGET_UID freerad || true
TARGET_GID=$(stat -c "%g" /etc/freeradius)
echo '-- Setting freerad group to use gid '$TARGET_GID
groupmod -o -g $TARGET_GID freerad || true

chmod 700 /etc/freeradius
chmod 700 /etc/freeradius/mods-config/files/authorize
chmod 777 /var/log/freeradius
chmod 666 /var/log/freeradius/radius.log

/usr/sbin/freeradius -f -xx
