#!/usr/bin/env python2
#
# update.py
#
# Part of the HoneySens SensorOS distribution for the BeagleBone Black platform.
# Downloads the current firmware for this sensor and performs an update.
#
# by Pascal Bruckner

import sys
import os
import tempfile
import shutil
import ConfigParser
import tarfile
import subprocess
import utils

if len(sys.argv) != 2 or not os.path.isfile(sys.argv[1]):
    print('Usage: update.py <config_file>')
    sys.exit(1)

config = ConfigParser.ConfigParser()
config.readfp(open(sys.argv[1]))

print('Polling first to refresh server status')
sys.argv = ['poll.py', sys.argv[1], True]
execfile('/opt/honeysens/poll.py')

tempdir = tempfile.mkdtemp()
firmware_url = 'api/sensorimages/download/by-sensor/{}'.format(config.get('general', 'sensor_id'))
print('Downloading firmware image from {}'.format(firmware_url))
try:
    with open('{}/firmware.tar.gz'.format(tempdir), 'wb') as f:
        utils.perform_https_request(config, firmware_url, utils.REQUEST_TYPE_GET, file_descriptor=f)
except Exception:
    print('Error: Firmware file couldn\'t be downloaded')
    shutil.rmtree(tempdir)
    sys.exit(1)

print('Inspecting archive')
tar = tarfile.open('{}/firmware.tar.gz'.format(tempdir))
try:
    files = tar.getnames()
    if 'firmware.img' not in files or 'metadata.xml' not in files:
        raise Exception()
except Exception:
    print('Error: Invalid firmware archive')
    shutil.rmtree(tempdir)
    sys.exit(1)
tar.close()

print('Writing image to microSD card')
ext_device = '/dev/mmcblk0'
int_device = '/dev/mmcblk1'
# Check for both internal and external block devices to avoid updating when no SD card is inserted
if not os.path.exists(ext_device) or not os.path.exists(int_device):
    print('Error: No microSD card found')
    shutil.rmtree(tempdir)
    sys.exit(1)
ret = subprocess.call(['/bin/tar', '-xf', '{}/firmware.tar.gz'.format(tempdir), '--to-command=dd bs=512k of={}'.format(ext_device), 'firmware.img'])
if ret != 0:
    print('Error: Can\'t write to microSD card')
    shutil.rmtree(tempdir)
    sys.exit(1)

print('Saving sensor configuration')
with open('{}/honeysens.cfg'.format(tempdir), 'wb') as f:
    config.write(f)
shutil.copy(config.get('server', 'certfile'), '{}/server-cert.pem'.format(tempdir))
shutil.copy(config.get('general', 'certfile'), '{}/cert.pem'.format(tempdir))
shutil.copy(config.get('general', 'keyfile'), '{}/key.pem'.format(tempdir))
with tarfile.open('{}/{}.tar.gz'.format(tempdir, config.get('general', 'hostname')), 'w:gz') as config_archive:
    for name in ['honeysens.cfg', 'server-cert.pem', 'cert.pem', 'key.pem']:
        config_archive.add('{}/{}'.format(tempdir, name), name)
print('  Mounting {}p1'.format(ext_device))
subprocess.call(['/sbin/partprobe', ext_device])
ret = subprocess.call(['/bin/mount', '{}p1'.format(ext_device), '/mnt'])
if ret != 0:
    print('Error: Can\'t mount microSD card')
    shutil.rmtree(tempdir)
    sys.exit(1)
shutil.copy('{}/{}.tar.gz'.format(tempdir, config.get('general', 'hostname')), '/mnt')
ret = subprocess.call(['/bin/umount', '/mnt'])
if ret != 0:
    print('Warning: Unmount of microSD card failed')

print('Cleaning up and triggering reboot')
shutil.rmtree(tempdir)
subprocess.call('/sbin/reboot')
