#!/usr/bin/env python2
#
# apply_config.py
#
# Part of the HoneySens SensorOS distribution.
# Applies a given sensor configuration.
#
# by Pascal Brueckner

import sys
import os
import ConfigParser
import vendor.debinterface.interfaces
import subprocess
import fileinput
import hashlib
import re


def is_ip(value):
    parts = value.split('.')
    if len(parts) == 4 and all([x.isdigit() for x in parts]):
        numbers = list(int(x) for x in parts)
        return all([num >= 0 and num <= 255 for num in numbers])
    return False


def set_interval(interval):
    hostname = open('/etc/hostname', 'r').read().strip()
    delayed_start = int(hashlib.sha1(hostname.encode('ascii')).hexdigest(), 16) % int(interval)
    with open('/etc/cron.d/honeysens-sensor', 'r') as f:
        fc = f.readlines()
    if fc[0].split()[0] != '{}-59/{}'.format(delayed_start, interval):
        with open('/etc/cron.d/honeysens-sensor', 'w') as f:
            f.write('{}-59/{} *   * * *   root    /opt/honeysens/poll.py /opt/honeysens/honeysens.cfg >/dev/null 2>&1\n'.format(delayed_start, interval))


def is_running(process_name):
    p = subprocess.Popen(['ps', 'axw'], stdout=subprocess.PIPE)
    out, err = p.communicate()
    for proc in out:
        if re.search(process_name, proc.decode('utf-8')):
            return True
    return False

if len(sys.argv) < 2 or not os.path.isfile(sys.argv[1]):
    print('Usage: apply_config.py <config_file> [<network_changed>]')
    sys.exit(1)

if is_running('apply_config.py'):
    print('Error: Multiple instances of apply_config.py can\'t be run simultaneously')
    sys.exit(1)

network_changed = False
if len(sys.argv) == 3:
    network_changed = sys.argv[2]

config = ConfigParser.ConfigParser()
config.readfp(open(sys.argv[1]))
network_interface = config.get('network', 'interface')
server_host = config.get('server', 'host')
server_name = config.get('server', 'name')

if network_changed:
    print('  ...stopping recon, kippo and dionaea and the network')
    subprocess.call(['/usr/sbin/invoke-rc.d', 'honeysens-sensor', 'recon', 'stop'])
    subprocess.call(['/usr/sbin/invoke-rc.d', 'honeysens-sensor', 'kippo', 'stop'])
    subprocess.call(['/usr/sbin/invoke-rc.d', 'honeysens-sensor', 'dionaea', 'stop'])
    subprocess.call(['/usr/sbin/invoke-rc.d', 'networking', 'stop'])
    print('Updating network configuration')
    ifaces = vendor.debinterface.interfaces.Interfaces()
    if ifaces.getAdapter(network_interface) is None:
        ifaces.addAdapter(network_interface, 0)
    adapter = ifaces.getAdapter(network_interface)
    adapter.setAddrFam('inet')
    if config.get('network', 'mode') == '0':
        adapter.setAddressSource('dhcp')
        adapter.setAddress(None)
        adapter.setNetmask(None)
        adapter.setGateway(None)
        # Debinterfaces is missing the option to remove 'unknown' attributes, therefore we hack this part
        if 'unknown' in adapter._ifAttributes:
            del(adapter._ifAttributes['unknown'])
    elif config.get('network', 'mode') == '1':
        adapter.setAddressSource('static')
        adapter.setAddress(config.get('network', 'address'))
        adapter.setNetmask(config.get('network', 'netmask'))
        gateway = config.get('network', 'gateway')
        if gateway:
            adapter.setGateway(gateway)
        else:
            adapter.setGateway(None)
        nameservers = config.get('network', 'dns')
        if nameservers:
            adapter.setUnknown('dns-nameservers', nameservers)
        else:
            # Debinterfaces is missing the option to remove 'unknown' attributes, therefore we hack this part
            if 'unknown' in adapter._ifAttributes:
                del(adapter._ifAttributes['unknown'])
    ifaces.writeInterfaces()

    if is_ip(server_host):
        print('Updating server endpoint in /etc/hosts')
        if 'honeysens-server' in open('/etc/hosts').read():
            # Adjust existing hosts entry
            f = fileinput.input('/etc/hosts', inplace=1)
            for line in f:
                if 'honeysens-server' in line and (server_host not in line or server_name not in line):
                    print '{}\t{} {}'.format(server_host, server_name, 'honeysens-server')
                else:
                    print line,
            f.close()
        else:
            # Add new hosts entry
            with open('/etc/hosts', 'a') as f:
                f.write('{}\t{} {}\n'.format(server_host, server_name, 'honeysens-server'))

print('Updating cron script')
set_interval(config.get('server', 'interval'))

if config.get('mac', 'mode') == '1':
    mac = config.get('mac', 'address')
    print('Changing MAC address to {}'.format(mac))
    subprocess.call(['/usr/bin/macchanger', '-m', mac, network_interface])

print('Restarting services')
if network_changed:
    print('  ...refreshing hostname')
    subprocess.call(['/usr/sbin/invoke-rc.d', 'hostname.sh', 'start'])
    print('  ...starting network')
    subprocess.call(['/usr/sbin/invoke-rc.d', 'networking', 'start'])
service_recon, service_kippo, service_dionaea = ('stop', 'stop', 'stop')
if config.getboolean('services', 'recon'):
    service_recon = 'start'
if config.getboolean('services', 'kippo'):
    service_kippo = 'start'
if config.getboolean('services', 'dionaea'):
    service_dionaea = 'start'
for service, run in [('recon', service_recon), ('kippo', service_kippo), ('dionaea', service_dionaea)]:
    subprocess.call(['/usr/sbin/invoke-rc.d', 'honeysens-sensor', service, run])

# Firmware update
current_version = open('/opt/honeysens/version.txt', 'r').read().strip()
if config.get('general', 'sw_version') != current_version and os.path.isfile('/opt/honeysens/update.py'):
    sys.argv = ['update.py', sys.argv[1]]
    execfile('/opt/honeysens/update.py')
