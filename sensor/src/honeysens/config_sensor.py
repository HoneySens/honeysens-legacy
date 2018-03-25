#!/usr/bin/env python2
#
# config_sensor.py
#
# Part of the HoneySens SensorOS distribution.
# Reconfigures the sensor if given a configuration archive.
#
# by Pascal Brueckner

import sys
import os
import tempfile
import ConfigParser
import tarfile
import fileinput
import shutil


if len(sys.argv) != 2 or not os.path.isfile(sys.argv[1]):
    print('Usage: config_sensor.py <config_archive>')
    sys.exit(1)

config_archive = os.path.abspath(sys.argv[1])
print('Configuration archive: {}'.format(config_archive))

tempdir = tempfile.mkdtemp()
tar = tarfile.open(config_archive)
tar.extractall(tempdir)
config = ConfigParser.ConfigParser()
config.readfp(open('{}/honeysens.cfg'.format(tempdir)))
server_host = config.get('server', 'host')
server_port_https = config.get('server', 'port_https')
sensor_hostname = config.get('general', 'hostname')
print('  API Endpoint: {}:{}'.format(server_host, server_port_https))
print('  Sensor Name:  {}\n'.format(sensor_hostname))

# Update hostname
print('Updating hostname')
old_hostname = None
with open('/etc/hostname', 'r') as f:
    old_hostname = f.readline().split()[0]
with open('/etc/hostname', 'w') as f:
    f.write(sensor_hostname)
f = fileinput.input('/etc/hosts', inplace=1)
for line in f:
    print line.replace(old_hostname, sensor_hostname),
f.close()

# Remove old server name from /etc/hosts
if 'honeysens-server' in open('/etc/hosts').read():
    print('Updating /etc/hosts')
    with open('/etc/hosts', 'r+') as f:
        lines = f.readlines()
        f.seek(0)
        for l in lines:
            if 'honeysens-server' not in l:
                f.write(l)
        f.truncate()

print('Saving sensor configuration')
with open('/opt/honeysens/honeysens.cfg', 'wb') as sensor_config:
    config.write(sensor_config)
shutil.copy('{}/cert.pem'.format(tempdir), '/opt/honeysens')
shutil.copy('{}/key.pem'.format(tempdir), '/opt/honeysens')
shutil.copy('{}/server-cert.pem'.format(tempdir), '/opt/honeysens')

print('Cleaning up')
shutil.rmtree(tempdir)

print('Applying sensor configuration')
sys.argv = ['apply_config.py', '/opt/honeysens/honeysens.cfg', True]
execfile('/opt/honeysens/apply_config.py')
