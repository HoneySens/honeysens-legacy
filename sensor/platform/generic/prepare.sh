#!/usr/bin/env sh

# Build honeyd
cd /root
git clone https://github.com/DataSoft/honeyd
cd /root/honeyd
patch -p1 < /root/honeyd.diff
./autogen.sh
./configure
make
make install

# Build sensor manager
mkdir /etc/manager
cd /opt/manager
python setup.py install
