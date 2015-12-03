#!/bin/bash

cd /freeradius-server
./configure --with-openssl --with-openssl-lib-dir=/usr/local/openssl/lib --with-openssl-include-dir=/usr/local/openssl/include/  
make
make install
