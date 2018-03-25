#!/bin/bash
set -e
if [[ ! -e /opt/HoneySens/data/ssl-cert.pem ]]; then
  openssl req -newkey rsa:4096 -nodes -sha256 -keyout /opt/HoneySens/data/ssl-cert.key -x509 -days 365 -out /opt/HoneySens/data/ssl-cert.pem -subj "/CN=$(hostname)"
fi
