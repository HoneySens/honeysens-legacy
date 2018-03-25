from __future__ import absolute_import

import json
import fcntl
import os
import socket
import struct
import subprocess
import tarfile
import threading
import time
import traceback

from . import hooks
from . import state
from .utils import communication
from .utils import constants


_timer = None
_config_dir = None
_config = None
_config_archive = None


def worker():
    global _timer
    # Send status data to server
    try:
        update_system_time()
        r = send_data(collect_data())
        result = json.loads(r['content'])
        network_changed = update_config(result)
        try:
            state.apply_config(_config, result, network_changed)
            hooks.execute_hook(constants.Hooks.ON_POLL, [result])
        except Exception as e:
            print('Warning: Exception when trying to apply new configuration ({})'.format(str(e)))
        next_execution = _config.getint('server', 'interval') * 60
    except Exception as e:
        # traceback.print_exc()
        print('Warning: Polling failed, retrying in 60 seconds ({})'.format(str(e)))
        # Retry in one minute if something fails (server unreachable, etc.)
        next_execution = 60

    # Reschedule worker
    _timer = threading.Timer(next_execution, worker, args=())
    _timer.setDaemon(True)
    _timer.start()


def get_ip_address(iface):
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    return socket.inet_ntoa(fcntl.ioctl(s.fileno(), 0x8915, struct.pack(b'256s', iface[:15].encode('utf-8')))[20:24])


def collect_data():
    p = subprocess.Popen(['free', '-m'], stdout=subprocess.PIPE)
    out, err = p.communicate()
    free_mem = out.decode('utf-8').split('\n')[2].split()[3]
    current_version = constants.SW_VERSION
    status_code = 1
    #if update_running:
        #status_code = 2
    return {'timestamp': int(time.time()),
            'status': status_code,
            'ip': get_ip_address(_config.get('network', 'interface')),
            'free_mem': free_mem,
            'sw_version': current_version}


def send_data(data):
    signing_key = open('{}/{}'.format(_config_dir, _config.get('general', 'keyfile')), 'r').read()
    post_data = {'sensor': _config.get('general', 'sensor_id'), 'status': communication.encode_data(json.dumps(data).encode('ascii')), 'signature': communication.sign_data(signing_key, data)}
    return communication.perform_https_request(_config, _config_dir, 'api/sensorstatus', communication.REQUEST_TYPE_POST, post_data=post_data)


def update_system_time():
    r = communication.perform_https_request(_config, _config_dir, '#', communication.REQUEST_TYPE_HEAD, verify=False)
    if 'date' not in r['headers']:
        return
    req_time = r['headers']['date']
    t = time.localtime(time.mktime(time.strptime(req_time, '%a, %d %b %Y %H:%M:%S %Z')) - time.timezone)
    subprocess.call(['date', '-s', time.strftime('%Y/%m/%d %H:%M:%S', t)])


def update_config(config_data):
    network_changed = False
    if 'sw_version' in config_data:
        _config.set('general', 'sw_version', str(config_data['sw_version']))
    if 'server_endpoint_host' in config_data and str(config_data['server_endpoint_host']) != _config.get('server', 'host'):
        _config.set('server', 'host', str(config_data['server_endpoint_host']))
        network_changed = True
    if 'server_endpoint_port_https' in config_data:
        _config.set('server', 'port_https', str(config_data['server_endpoint_port_https']))
    if 'interval' in config_data:
        _config.set('server', 'interval', str(config_data['interval']))
    if 'network_ip_mode' in config_data and str(config_data['network_ip_mode']) != _config.get('network', 'mode'):
        _config.set('network', 'mode', str(config_data['network_ip_mode']))
        network_changed = True
    if 'network_ip_address' in config_data and str(config_data['network_ip_address']) != _config.get('network', 'address'):
        _config.set('network', 'address', str(config_data['network_ip_address']))
        network_changed = True
    if 'network_ip_netmask' in config_data and str(config_data['network_ip_netmask']) != _config.get('network', 'netmask'):
        _config.set('network', 'netmask', str(config_data['network_ip_netmask']))
        network_changed = True
    if 'network_ip_gateway' in config_data and str(config_data['network_ip_gateway']) != _config.get('network', 'gateway'):
        _config.set('network', 'gateway', str(config_data['network_ip_gateway']))
        network_changed = True
    if 'network_ip_dns' in config_data and str(config_data['network_ip_dns']) != _config.get('network', 'dns'):
        _config.set('network', 'dns', str(config_data['network_ip_dns']))
        network_changed = True
    if 'network_mac_mode' in config_data and str(config_data['network_mac_mode']) != _config.get('mac', 'mode'):
        _config.set('mac', 'mode', str(config_data['network_mac_mode']))
        network_changed = True
    if 'network_mac_address' in config_data and str(config_data['network_mac_address']) != _config.get('mac', 'address'):
        _config.set('mac', 'address', str(config_data['network_mac_address']))
        network_changed = True
    if 'proxy_mode' in config_data and str(config_data['proxy_mode']) != _config.get('proxy', 'mode'):
        _config.set('proxy', 'mode', str(config_data['proxy_mode']))
        network_changed = True
    if 'proxy_host' in config_data and str(config_data['proxy_host']) != _config.get('proxy', 'host'):
        _config.set('proxy', 'host', str(config_data['proxy_host']))
        network_changed = True
    if 'proxy_port' in config_data and str(config_data['proxy_port']) != _config.get('proxy', 'port'):
        _config.set('proxy', 'port', str(config_data['proxy_port']))
        network_changed = True
    if 'proxy_user' in config_data and str(config_data['proxy_user']) != _config.get('proxy', 'user'):
        _config.set('proxy', 'user', str(config_data['proxy_user']))
    if 'proxy_password' in config_data and str(config_data['proxy_password']) != _config.get('proxy', 'password'):
        _config.set('proxy', 'password', str(config_data['proxy_password']))
    if 'recon' in config_data:
        _config.set('services', 'recon', str(config_data['recon']))
    if 'kippoHoneypot' in config_data:
        _config.set('services', 'kippo', str(config_data['kippoHoneypot']))
    if 'dionaeaHoneypot' in config_data:
        _config.set('services', 'dionaea', str(config_data['dionaeaHoneypot']))
    # Rewrite config archive
    # TODO Track if a config option was changed and only do that when necessary
    with tarfile.open(_config_archive, 'w:gz') as config_archive:
        for f in os.listdir(_config_dir):
            config_archive.add('{}/{}'.format(_config_dir, f), f)
    return network_changed


def start(config_dir, config, config_archive):
    global _config_dir, _config, _config_archive
    print('Starting polling worker')
    _config_dir = config_dir
    _config = config
    _config_archive = config_archive
    worker()
