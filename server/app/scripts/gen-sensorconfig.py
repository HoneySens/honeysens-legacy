#!/usr/bin/env python2
import sys, tempfile, shutil, subprocess, urlparse, socket
if not len(sys.argv) in (8, 10):
  print('Usage: gen-sensorconfig.py <configdir> <name> <cert> <key> <server-cert> <server-url> <dhcp|static> [<ip> <netmask>]')
  exit()
temp = tempfile.mkdtemp();
template = '{}/template'.format(sys.argv[1])
url = urlparse.urlparse(sys.argv[6])
print('Saving hostname')
with open('{}/hostname'.format(temp), 'w') as f:
  f.write(sys.argv[2])
print('Saving certificate data')
with open('{}/cert.pem'.format(temp), 'w') as f:
  f.write(sys.argv[3])
with open('{}/key.pem'.format(temp), 'w') as f:
  f.write(sys.argv[4])
with open('{}/server-cert.pem'.format(temp), 'w') as f:
  f.write(sys.argv[5])
print('Collecting template')
subprocess.call(['/bin/cp', '{}/honeysens.cfg'.format(template), '{}/interfaces'.format(template), temp])
print('Customizing template')
subprocess.call(['/bin/sed', '-i', 's/host = SERVERHOST/host = {}/'.format(url.netloc), '{}/honeysens.cfg'.format(temp)])
subprocess.call(['/bin/sed', '-i', 's|url = SERVERURL|url = {}|'.format(url.scheme + '://honeysens-server' + url.path), '{}/honeysens.cfg'.format(temp)])
subprocess.call(['/bin/sed', '-i', 's/iface eth0 inet MODE/iface eth0 inet {}/'.format(sys.argv[7]), '{}/interfaces'.format(temp)])
if sys.argv[7] == 'static':
  subprocess.call(['/bin/sed', '-i', 's/#    address IP/    address {}/'.format(sys.argv[8]), '{}/interfaces'.format(temp)])
  subprocess.call(['/bin/sed', '-i', 's/#    netmask NETMASK/    netmask {}/'.format(sys.argv[9]), '{}/interfaces'.format(temp)])
print('Creating config archive')
subprocess.call(['/bin/tar', 'czf', '{}/honeysens.cfg.tar.gz'.format(temp), '--directory', temp, 'hostname', 'cert.pem', 'key.pem', 'server-cert.pem', 'interfaces', 'honeysens.cfg'])
subprocess.call(['/bin/mv', '{}/honeysens.cfg.tar.gz'.format(temp), '{}/{}.cfg.tar.gz'.format(sys.argv[1], sys.argv[2])])
print('Done, archive saved as {}.cfg.tar.gz'.format(sys.argv[2]))
shutil.rmtree(temp)
