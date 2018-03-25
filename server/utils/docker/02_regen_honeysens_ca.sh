#!/bin/bash
set -e
if [[ ! -e /opt/HoneySens/data/CA/cakey.pem ]]; then
  cd /opt/HoneySens/data/CA
  openssl req -nodes -new -x509 -extensions v3_ca -keyout cakey.pem -out cacert.pem -days 365 -config ./openssl.cnf -subj "/C=DE/ST=Saxony/L=Dresden/O=SID/CN=HoneySens"
fi