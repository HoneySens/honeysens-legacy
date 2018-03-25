#!/usr/bin/env python2
#
# poll.py
#
# Part of the HoneySens SensorOS distribution.
# Sends the current sensor status to the server and parses received configuration data.
#
# by Pascal Brueckner

import ConfigParser
import sys
import os
import subprocess
import time
import socket
import fcntl
import struct
import base64
import json
import re
import utils
from Crypto.PublicKey import RSA
from Crypto.Hash import SHA
from Crypto.Signature import PKCS1_v1_5


def is_running(process_name, occurences=1):
    actual_occurences = 0
    p = subprocess.Popen(['ps', 'axw'], stdout=subprocess.PIPE)
    out, err = p.communicate()
    for proc in out.split('\n'):
        if re.search(process_name, proc.decode('utf-8')):
            actual_occurences += 1
    return actual_occurences >= occurences


def get_ip_address(iface):
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    return socket.inet_ntoa(fcntl.ioctl(s.fileno(), 0x8915, struct.pack(b'256s', iface[:15].encode('utf-8')))[20:24])


def collect_data(config):
    p = subprocess.Popen(['free', '-m'], stdout=subprocess.PIPE)
    out, err = p.communicate()
    free_mem = out.decode('utf-8').split('\n')[2].split()[3]
    current_version = open('/opt/honeysens/version.txt', 'r').read().strip()
    status_code = 1
    if update_running:
        status_code = 2
    return {'timestamp': int(time.time()),
            'status': status_code,
            'ip': get_ip_address(config.get('network', 'interface')),
            'free_mem': free_mem,
            'sw_version': current_version}


def send_data(config, data):
    key = RSA.importKey(open(config.get('general', 'keyfile'), 'r').read())
    signer = PKCS1_v1_5.new(key)
    digest = SHA.new()
    digest.update(json.dumps(data).encode('utf-8'))
    sign = signer.sign(digest)
    post_data = {'sensor': config.get('general', 'sensor_id'), 'status': base64.b64encode(json.dumps(data).encode('ascii')).decode('utf-8'), 'signature': base64.b64encode(sign).decode('utf-8')}
    return utils.perform_https_request(config, 'api/sensorstatus', utils.REQUEST_TYPE_POST, post_data=post_data)


def refresh_date():
    r = utils.perform_https_request(config, '#', utils.REQUEST_TYPE_HEAD, verify=False)
    if 'date' not in r['headers']:
        return
    req_time = r['headers']['date']
    t = time.localtime(time.mktime(time.strptime(req_time, '%a, %d %b %Y %H:%M:%S %Z')) - time.timezone)
    subprocess.call(['date', '-s', time.strftime('%Y/%m/%d %H:%M:%S', t)])

if len(sys.argv) < 2 or not os.path.isfile(sys.argv[1]):
    print('Usage: poll.py <config_file> [update_running]')
    sys.exit(1)

if is_running('config_sensor.py') or is_running('update.py'):
    print('Error: Other sensor configuration scripts are running already')
    sys.exit(1)

config = ConfigParser.ConfigParser()
config.readfp(open(sys.argv[1]))
server_name = config.get('server', 'name')
server_port_https = config.get('server', 'port_https')
update_running = False

if len(sys.argv) == 3:
    update_running = sys.argv[2]

# Since this script is usually run by cron, its name will occupy two lines in the process table.
# That's why we check for three or more instances to detect an already running upgrade process.
if is_running('poll.py', 3):
    print('Warning: poll.py is already running, assuming update')
    update_running = True

print('Refreshing local time')
refresh_date()

print('Sending status data to {}:{}'.format(server_name, server_port_https))
r = send_data(config, collect_data(config))

print('Parsing result')
result = json.loads(r['content'])

network_changed = False

if 'sw_version' in result:
    config.set('general', 'sw_version', str(result['sw_version']))
if 'server_endpoint_host' in result and str(result['server_endpoint_host']) != config.get('server', 'host'):
    config.set('server', 'host', str(result['server_endpoint_host']))
    network_changed = True
if 'server_endpoint_port_https' in result:
    config.set('server', 'port_https', str(result['server_endpoint_port_https']))
if 'interval' in result:
    config.set('server', 'interval', str(result['interval']))
if 'network_ip_mode' in result and str(result['network_ip_mode']) != config.get('network', 'mode'):
    config.set('network', 'mode', str(result['network_ip_mode']))
    network_changed = True
if 'network_ip_address' in result and str(result['network_ip_address']) != config.get('network', 'address'):
    config.set('network', 'address', str(result['network_ip_address']))
    network_changed = True
if 'network_ip_netmask' in result and str(result['network_ip_netmask']) != config.get('network', 'netmask'):
    config.set('network', 'netmask', str(result['network_ip_netmask']))
    network_changed = True
if 'network_ip_gateway' in result and str(result['network_ip_gateway']) != config.get('network', 'gateway'):
    config.set('network', 'gateway', str(result['network_ip_gateway']))
    network_changed = True
if 'network_ip_dns' in result and str(result['network_ip_dns']) != config.get('network', 'dns'):
    config.set('network', 'dns', str(result['network_ip_dns']))
    network_changed = True
if 'network_mac_mode' in result and str(result['network_mac_mode']) != config.get('mac', 'mode'):
    config.set('mac', 'mode', str(result['network_mac_mode']))
    network_changed = True
if 'network_mac_address' in result and str(result['network_mac_address']) != config.get('mac', 'address'):
    config.set('mac', 'address', str(result['network_mac_address']))
    network_changed = True
if 'proxy_mode' in result and str(result['proxy_mode']) != config.get('proxy', 'mode'):
    config.set('proxy', 'mode', str(result['proxy_mode']))
    network_changed = True
if 'proxy_host' in result and str(result['proxy_host']) != config.get('proxy', 'host'):
    config.set('proxy', 'host', str(result['proxy_host']))
    network_changed = True
if 'proxy_port' in result and str(result['proxy_port']) != config.get('proxy', 'port'):
    config.set('proxy', 'port', str(result['proxy_port']))
    network_changed = True
if 'proxy_user' in result and str(result['proxy_user']) != config.get('proxy', 'user'):
    config.set('proxy', 'user', str(result['proxy_user']))
if 'proxy_password' in result and str(result['proxy_password']) != config.get('proxy', 'password'):
    config.set('proxy', 'password', str(result['proxy_password']))
if 'recon' in result:
    config.set('services', 'recon', str(result['recon']))
if 'kippoHoneypot' in result:
    config.set('services', 'kippo', str(result['kippoHoneypot']))
if 'dionaeaHoneypot' in result:
    config.set('services', 'dionaea', str(result['dionaeaHoneypot']))


with open(sys.argv[1], 'w') as f:
    config.write(f)

if not update_running:
    print('Applying sensor configuration')
    sys.argv = ['apply_config.py', sys.argv[1], network_changed]
    execfile('/opt/honeysens/apply_config.py')
