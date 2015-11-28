FROM ubuntu:trusty
MAINTAINER Michael Russell

# Set noninteractive mode for apt-get
ENV DEBIAN_FRONTEND noninteractive

# Upgrade base system packages
RUN apt-get update

# install freeradius
RUN apt-get -y install software-properties-common build-essential\
    && add-apt-repository ppa:freeradius/stable-3.0 \
    && apt-get update \
    && apt-get -y install freeradius freeradius-mysql

# Clean up APT when done.
#RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# eventually when we have a known config - is it better to install the freeradius config file
# into the container - rather than use a mount?
# Add files
#ADD install.sh /opt/install.sh
#CMD /opt/install.sh;


COPY freeradius/fix_permissions.sh /tmp/fix_permissions.sh
RUN chmod 755 /tmp/fix_permissions.sh 

#ENTRYPOINT ["/tmp/fix_permissions.sh"]
CMD /tmp/fix_permissions.sh

EXPOSE 1812/udp
EXPOSE 1813/udp

#CMD /usr/sbin/freeradius -f -xx
