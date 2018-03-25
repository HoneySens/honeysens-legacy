#!/usr/bin/env python2

# Not-so-passive service that collects information about received IP packets that aren't
# directed at one of the local listening services and reports them to the HoneySens server.
# It answers packets that are:
# - directed at the local host
# - not directed at one of the local services
# - not received from or sent to the HoneySens server

import subprocess
import re
import random
import sys
import signal
import ConfigParser
import os
import socket
import time
import base64
import json
import threading
import netifaces
import dns.resolver
import utils as hs_utils
from scapy.all import *
from Crypto.PublicKey import RSA
from Crypto.Hash import SHA
from Crypto.Signature import PKCS1_v1_5

# Constants
SERVICE_ID = 0            # Internal HoneySens ID to identify data from this service
SEND_WAIT_TIMEOUT = 6     # Only send an event if it didn't receive new packets for x seconds
FLOODER_CAP = 25          # Packet threshold to classify a remote host as a flooder and the event as a scan
FLOODER_TIMEOUT = 60      # Seconds to wait until packets from a flooder are accepted again

# Globals
seq = random.randint(0, 10000)                          # sequence number for generated TCP packets
netfilter_chain_name = 'honeysens-recon'                # iptables chain name
iptables_exec = '/sbin/iptables'                        # path to iptables binary
netstat_exec = '/bin/netstat'                           # path to netstat binary
listening_ports_tcp = []                                # TCP ports used by other processes
listening_ports_udp = []                                # UDP ports used by other processes
events = {}                                             # Currently "active" events, e.g. TCP connections including all related packages
flooders = {}                                           # Remote hosts that have been flooding this sensor and are now ignored
sensor_id = None                                        # ID of this sensor
sensor_iface = None                                     # Network interface to listen on
sensor_ip = None                                        # IP to imitiate
sensor_key = None                                       # Private sensor key
server_name = None                                      # API Server hostname
server_port = None                                      # API Server port
server_ip = None                                        # HoneySens server IP address
safe_hosts = []                                         # List of trusted hosts, which are ignored during packet inspection
server_certfile = None                                  # Path to HoneySens server certificate
worker_thread = None                                    # Reference to the event worker thread

# Parse Configuration
config = ConfigParser.ConfigParser()
try:
  with open(os.path.join(os.path.dirname(__file__), 'honeysens.cfg')) as f:
    config.readfp(f)
    sensor_key = RSA.importKey(open(config.get('general', 'keyfile'), 'r').read())
    server_name = config.get('server', 'name')
    server_port = config.get('server', 'port_https')
    server_ip = socket.gethostbyname(server_name)
    safe_hosts.append(server_ip)
    server_certfile = config.get('server', 'certfile')
    sensor_id = config.get('general', 'sensor_id')
    sensor_iface = config.get('network', 'interface')
    sensor_ip = netifaces.ifaddresses(sensor_iface)[2][0]['addr']
    # Add DNS servers to the whitelist
    resolver = dns.resolver.Resolver()
    for ns in resolver.nameservers:
        safe_hosts.append(ns)
    # Add proxies to the whitelist
    if config.get('proxy', 'mode') == '1':
        safe_hosts.append(socket.gethostbyname(config.get('proxy', 'host')))
    print('HoneySens Configuration\n  Server Name: {}\n  Server IP: {}\n  Server certificate: {}\n  Sensor ID: {}\n  Sensor interface: {}\n  Sensor IP: {}').format(server_name, server_ip, server_certfile, sensor_id, sensor_iface, sensor_ip)
    for host in safe_hosts:
        print('  Whitelisted: {}'.format(host))
except Exception:
  print('Error: Invalid HoneySens configuration')
  sys.exit(1)

# Thread that does the collection, classification and notification of events
def worker():
  global worker_thread
  worker_thread = threading.Timer(1, worker)
  worker_thread.setDaemon(True)
  worker_thread.start()
  send_canidates = []
  # Process the event queue
  for src, e in events.iteritems():
    # Cap max number of packets per event at FLOODER_CAP, classify the event as a scan and put the remote host into the flooder list
    if len(e['packets']) >= FLOODER_CAP and src not in flooders:
      print('Too many packets from {}, classifying as scan and ignoring for {} seconds'.format(src, FLOODER_TIMEOUT))
      e['summary'] = 'Scan'
      flooders[src] = int(time.time())
    # Put all events that didn't receive packages within the last SEND_WAIT_TIMEOUT seconds into the 'send' queue
    if int(time.time()) - e['packets'][-1]['timestamp'] >= SEND_WAIT_TIMEOUT:
      send_canidates.append(e)
  # Process the send queue
  if len(send_canidates) > 0:
    print('Sending {} collected events to the server'.format(len(send_canidates)))
    for c in send_canidates:
      events.pop(c['source'])
      event_data = {'sensor': sensor_id, 'events': base64.b64encode(json.dumps(send_canidates).encode('ascii')).decode('utf-8')}
      signer = PKCS1_v1_5.new(sensor_key)
      digest = SHA.new()
      digest.update(json.dumps(send_canidates).encode('utf-8'))
      sign = signer.sign(digest)
      event_data['signature'] = base64.b64encode(sign).decode('utf-8')
      hs_utils.perform_https_request(config, 'api/events', hs_utils.REQUEST_TYPE_POST, post_data=event_data)
      try:
        os.system('/opt/honeysens/led_alert.sh')
      except:
        pass
  # Process the flooder queue
  for src, f in list(flooders.iteritems()):
    # Remove remote host blocks after FLOODER_TIMEOUT seconds
    if (int(time.time()) - f) >= FLOODER_TIMEOUT:
      print('Host {} is not ignored anymore'.format(src))
      del flooders[src]

def get_event(src_ip):
  # Look up an existing event or create a new one if necessary
  if src_ip in events:
    incident_data = events[src_ip]
  else:
    incident_data = {'packets': [], 'details': [], 'service': SERVICE_ID, 'source': src_ip, 'summary': 'Einzelverbindung'}
  return incident_data

# Packet event handler, executed for each received packet
def packet_handler(p):
  global seq
  incident_detected = False
  incident_data = {}
  # Only react to IP packets that are
  # - sent directly to this host (no broadcasts or multicasts)
  # - not sent from the HoneySens server
  # - not in the list of ignored flooders
  if IP in p and p[IP].dst == sensor_ip and p[IP].src not in safe_hosts and p[IP].src not in flooders:
    src_ip = p[IP].src
    # TCP packets that aren't directed at one of the TCP ports of other listening services
    if TCP in p and str(p[TCP].dport) not in listening_ports_tcp:
      incident_detected = True
      incident_data = get_event(src_ip)
      packet = {'headers': [{'flags': p[TCP].flags}], 'protocol': 1, 'port': p[TCP].dport, 'timestamp': int(time.time()), 'payload': None}
      if p[TCP].flags == 0x02: # SYN
        send(IP(src=p[IP].dst, dst=p[IP].src)/TCP(flags="SA", sport=p[TCP].dport, dport=p[TCP].sport, ack=p[TCP].seq+1, seq=seq))
        seq = seq + 1
      elif p[TCP].flags == 0x011: # FIN-ACK
        # TODO state machine -> check for handshake
        send(IP(src=p[IP].dst, dst=p[IP].src)/TCP(flags="FA", sport=p[TCP].dport, dport=p[TCP].sport, ack=p[TCP].seq+1, seq=seq))
      elif str(p[TCP].payload) != '':
        #if p[TCP].flags in [0x10, 0x18]: # ACK or PSH-ACK
          #send(IP(src=p[IP].dst, dst=p[IP].src)/TCP(flags="A", sport=p[TCP].dport, dport=p[TCP].sport, ack=p[TCP].seq+len(p[TCP].load), seq=seq))
        # Save payload and reset connection
        if hasattr(p[TCP], 'payload') and hasattr(p[TCP], 'load'):
            packet['payload'] = base64.b64encode(p[TCP].payload.load)
        send(IP(src=p[IP].dst, dst=src_ip)/TCP(flags="R", sport=p[TCP].dport, dport=p[TCP].sport, ack=p[TCP].seq+len(p[TCP].load), seq=seq))
        #print('TCP payload: {}'.format(p[TCP].payload))
      incident_data['packets'].append(packet)
    # UDP packets that aren't directed at one of the UDP ports of other listening services
    elif UDP in p and str(p[UDP].dport) not in listening_ports_udp:
      incident_detected = True
      incident_data = get_event(src_ip)
      payload = None
      if hasattr(p[UDP], 'payload') and hasattr(p[UDP].payload, 'load'):
          payload = base64.b64encode(p[UDP].payload.load)
      packet = {'headers': [], 'protocol': 2, 'port': p[UDP].dport, 'timestamp': int(time.time()), 'payload': payload}
      #print('UDP payload: {}'.format(p[UDP].payload.load))
      incident_data['packets'].append(packet)
  if incident_detected:
    # Add a new event if this packet belongs to an so far unknown remote host
    if src_ip not in events:
      incident_data['packets'].append(packet)
      incident_data['timestamp'] = int(time.time())
      events[src_ip] = incident_data

# Collect a list of already opened local ports
for protocol in ['t', 'u']:
  ports = listening_ports_tcp
  if protocol == 'u':
    ports = listening_ports_udp
  p = subprocess.Popen([netstat_exec, '-lnp{}A'.format(protocol), 'inet,inet6'], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
  out, err = p.communicate()
  lines = out.split('\n')[2:]
  for line in lines:
    if len(line.split()) > 3:
      port = re.search(':([0-9]*$)', line.split()[3]).group(1)
      if port not in ports:
        ports.append(port)
print('Detected listening ports:\n  TCP: {}\n  UDP: {}'.format(listening_ports_tcp, listening_ports_udp))

# Add netfilter rules to block all outgoing DROP packets except for the local listening ports
print('Setting up netfilter rules...')
p = subprocess.call([iptables_exec, '-N', netfilter_chain_name])
if p == 1:
  print('  Chain already exists: flushing existing rules')
  subprocess.call([iptables_exec, '-F', netfilter_chain_name])
subprocess.call([iptables_exec, '-A', netfilter_chain_name, '-j', 'DROP'])
if subprocess.call([iptables_exec, '-C', 'OUTPUT', '-p', 'tcp', '--tcp-flags', 'RST', 'RST', '-j', netfilter_chain_name], stderr=subprocess.PIPE) == 1:
  subprocess.call([iptables_exec, '-A', 'OUTPUT', '-p', 'tcp', '--tcp-flags', 'RST', 'RST', '-j', netfilter_chain_name])
for port in listening_ports_tcp:
  print('  Whitelisting outgoing RST packets from TCP port {}'.format(port))
  subprocess.call([iptables_exec, '-I', netfilter_chain_name, '-p', 'tcp', '--sport', port, '-j', 'ACCEPT'])

# Register SIGTERM handler for clean shutdown
def sigtermhandler(signal, frame):
  print('Performing shutdown')
  subprocess.call([iptables_exec, '-D', 'OUTPUT', '-p', 'tcp', '--tcp-flags', 'RST', 'RST', '-j', netfilter_chain_name])
  subprocess.call([iptables_exec, '-F', netfilter_chain_name])
  subprocess.call([iptables_exec, '-X', netfilter_chain_name])
  sys.exit(0)
signal.signal(signal.SIGTERM, sigtermhandler)
signal.signal(signal.SIGINT, sigtermhandler)

# Launch worker thread
worker()

sniff(iface=sensor_iface, prn=packet_handler, store=0)
