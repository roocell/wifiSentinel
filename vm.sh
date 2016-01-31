#!/bin/bash
# this script creates a dinghy docker-machine with a 3rd bridged adapter
# which is used to get direct access to the freeradius server so that
# the outgoing radius packets have the radius port as the src port.
# the docker-machine was NAT'ing these packets, thus creating a random port (airport extreme does not like this)

if [ "$1"="destroy" ]; then
    echo "DESTROYING DOCKER-MACHINE"
    dinghy destroy
else
    echo "CREATING DOCKER-MACHINE"
    dinghy create --provider virtualbox --memory 512
    docker-machine stop dinghy
    VBoxManage modifyvm dinghy --nic3 bridged --bridgeadapter3 en0
    docker-machine start dinghy
    #sleep 10

    # set static ip and kill the dhcp client    
    echo "kill `more /var/run/udhcpc.eth1.pid`\nifconfig eth1 192.168.99.50 netmask 255.255.255.0 broadcast 192.168.99.255 up" | docker-machine ssh dinghy sudo tee /var/lib/boot2docker/bootsync.sh > /dev/null
    docker-machine ssh dinghy "sudo ifconfig eth1 192.168.1.11 netmask 255.255.255.0 broadcast 192.168.1.255 up"

    docker-machine regenerate-certs dinghy
    sleep 1
    eval "$(docker-machine env dinghy)"
    docker-machine ls
    
    # kill the http that comes with dinghy
    docker rm -f dinghy_http_proxy
fi
