#!/bin/bash
# this script creates a dinghy docker-machine with a 3rd bridged adapter
# which is used to get direct access to the freeradius server so that
# the outgoing radius packets have the radius port as the src port.
# the docker-machine was NAT'ing these packets, thus creating a random port (airport extreme does not like this)

if [ $1 == "destroy" ]; then
    echo "DESTROYING DOCKER-MACHINE"
    dinghy destroy
else
    echo "CREATING DOCKER-MACHINE"
    dinghy create --provider virtualbox --memory 512
    docker-machine stop dinghy
    VBoxManage modifyvm dinghy --nic3 bridged --bridgeadapter3 en0
    docker-machine start dinghy
    docker-machine regenerate-certs dinghy
    eval "$(docker-machine env dinghy)"
    docker-machine ls
fi
