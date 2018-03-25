import os
import uuid
import time
import zmq
from cowrie.core import dblog


class DBLogger(dblog.DBLogger):
    def __init__(self, cfg):
        self.disabled = False
        self.collector_host = None
        self.collector_port = None
        self.sessions = {}
        self.zmq_context = None
        dblog.DBLogger.__init__(self, cfg)

    def start(self, cfg):
        self.disabled = False
        if 'COLLECTOR_HOST' not in os.environ or 'COLLECTOR_PORT' not in os.environ:
            print('Error: No HoneySens collector specified, logging module disabled')
            self.disabled = True
        else:
            self.collector_host = os.environ['COLLECTOR_HOST']
            self.collector_port = os.environ['COLLECTOR_PORT']
            self.zmq_context = zmq.Context()
            print('HoneySens collector available at tcp://{}:{}'.format(self.collector_host, self.collector_port))

    def createSession(self, peerIP, peerPort, hostIP, hostPort):
        if self.disabled:
            return
        sid = uuid.uuid4().hex
        messages = [
            {'timestamp': int(time.time()), 'data': 'New connection: {}:{}'.format(peerIP, peerPort), 'type': 1}]
        self.sessions[sid] = {'source': peerIP, 'messages': messages}
        return sid

    def handleConnectionLost(self, session, args):
        if session not in self.sessions:
            return
        self.sessions[session]['messages'].append({'timestamp': int(time.time()), 'data': 'Connection Lost', 'type': 1})
        messages = self.sessions[session]['messages']
        event = {'timestamp': messages[0]['timestamp'], 'source': self.sessions[session]['source'], 'service': 1,
                 'summary': 'SSH', 'details': messages, 'packets': []}
        # Collector connection
        socket = self.zmq_context.socket(zmq.REQ)
        # TODO Error handling
        socket.connect("tcp://{}:{}".format(self.collector_host, self.collector_port))
        socket.send_json(event)
        # TODO This BLOCKS in case there is no response (e.g. error on collector)
        socket.recv()

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
                {'timestamp': int(time.time()),
                 'data': 'File download: [{}] -> {}'.format(args['url'], args['outfile']),
                 'type': 1})
