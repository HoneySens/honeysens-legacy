import os
import uuid
import json
import time
import base64
import ConfigParser
import sys
from cowrie.core import dblog
from Crypto.PublicKey import RSA
from Crypto.Hash import SHA
from Crypto.Signature import PKCS1_v1_5
from Crypto import Random

# Import common tools
sys.path.append('/opt/honeysens')
import utils


class DBLogger(dblog.DBLogger):
    def __init__(self, cfg):
        self.disabled = False
        self.server_name = None
        self.key = None
        self.servercert = None
        self.sensor_id = None
        self.sessions = {}
        self.config = None
        dblog.DBLogger.__init__(self, cfg)

    def start(self, cfg):
        self.disabled = False
        self.config = ConfigParser.ConfigParser()
        try:
            with open(cfg.get('database_honeysens', 'config')) as f:
                self.config.readfp(f)
                self.server_name = self.config.get('server', 'name')
                self.key = RSA.importKey(open(self.config.get('general', 'keyfile'), 'r').read())
                self.servercert = self.config.get('server', 'certfile')
                self.sensor_id = self.config.get('general', 'sensor_id')
                print(
                    'HoneySens Configuration\n Server: {}\n Key file: {}\n Server certificate: {}\n Sensor ID: {}').format(
                    self.server_name, self.key, self.servercert, self.sensor_id)
        except Exception:
            print('Error: Invalid HoneySens configuration, module disabled')
            self.disabled = True

    def createSession(self, peerIP, peerPort, hostIP, hostPort):
        if self.disabled:
            return
        sid = uuid.uuid4().hex
        messages = [
            {'timestamp': int(time.time()), 'data': 'New connection: {}:{}'.format(peerIP, peerPort), 'type': 1}]
        self.sessions[sid] = {'source': peerIP, 'messages': messages}
        Random.atfork()
        return sid

    def handleConnectionLost(self, session, args):
        if session not in self.sessions:
            return
        self.sessions[session]['messages'].append({'timestamp': int(time.time()), 'data': 'Connection Lost', 'type': 1})
        messages = self.sessions[session]['messages']
        event = [
            {'timestamp': messages[0]['timestamp'], 'source': self.sessions[session]['source'], 'service': 1,
             'summary': 'SSH', 'details': messages, 'packets': []}]
        events = {'sensor': self.sensor_id, 'events': base64.b64encode(json.dumps(event).encode('ascii')).decode('utf-8')}
        signer = PKCS1_v1_5.new(self.key)
        digest = SHA.new()
        digest.update(json.dumps(event).encode('utf-8'))
        sign = signer.sign(digest)
        events['signature'] = base64.b64encode(sign).decode('utf-8')
        utils.perform_https_request(self.config, 'api/events', utils.REQUEST_TYPE_POST, post_data=events)
        try:
            os.system('/opt/honeysens/led_alert.sh')
        except:
            pass

    def handleLoginFailed(self, session, args):
        if session in self.sessions:
            self.sessions[session]['messages'].append(
                {'timestamp': int(time.time()),
                 'data': 'Login failed [{}/{}]'.format(args['username'], args['password']),
                 'type': 1})

    def handleLoginSucceeded(self, session, args):
        if session in self.sessions:
            self.sessions[session]['messages'].append({'timestamp': int(time.time()),
                                                       'data': 'Login succeeded [{}/{}]'.format(args['username'],
                                                                                                args['password']),
                                                       'type': 1})

    def handleCommand(self, session, args):
        if session in self.sessions:
            self.sessions[session]['messages'].append(
                {'timestamp': int(time.time()), 'data': 'Command [{}]'.format(args['input']), 'type': 1})

    def handleUnknownCommand(self, session, args):
        if session in self.sessions:
            self.sessions[session]['messages'].append(
                {'timestamp': int(time.time()), 'data': 'Unknown command [{}]'.format(args['input']), 'type': 1})

    def handleInput(self, session, args):
        if session in self.sessions:
            self.sessions[session]['messages'].append(
                {'timestamp': int(time.time()), 'data:': 'Input [{}] @{}'.format(args['input'], args['realm']),
                 'type': 1})

    def handleTerminalSize(self, session, args):
        if session in self.sessions:
            self.sessions[session]['messages'].append(
                {'timestamp': int(time.time()), 'data': 'Terminal size: {}{}'.format(args['width'], args['height']),
                 'type': 1})

    def handleClientVersion(self, session, args):
        if session in self.sessions:
            self.sessions[session]['messages'].append(
                {'timestamp': int(time.time()), 'data': 'Client version: [{}]'.format(args['version']), 'type': 1})

    def handleFileDownload(self, session, args):
        if session in self.sessions:
            self.sessions[session]['messages'].append(
                {'timestamp': int(time.time()), 'data': 'File download: [{}] -> {}'.format(args['url'], args['outfile']),
                 'type': 1})
