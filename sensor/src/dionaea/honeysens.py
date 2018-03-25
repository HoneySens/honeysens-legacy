import os
import logging
import time
import base64
import json
import configparser
import sys
from dionaea.core import ihandler, g_dionaea
from dionaea.smb import smb
from Crypto.PublicKey import RSA
from Crypto.Hash import SHA
from Crypto.Signature import PKCS1_v1_5

# Import common tools
sys.path.append('/opt/honeysens')
import utils


class handler(ihandler):
    def __init__(self, path):
        self.disabled = False
        self.attacks = {}
        self.logger = logging.getLogger('honeysens')
        self.logger.setLevel(logging.WARNING)
        self.config = configparser.ConfigParser()
        try:
            with open(g_dionaea.config()['modules']['python']['honeysens']['config']) as f:
                self.config.read_file(f)
                self.server_name = self.config.get('server', 'name')
                self.key = RSA.importKey(open(self.config.get('general', 'keyfile'), 'r').read())
                self.servercertfile = self.config.get('server', 'certfile')
                self.sensor_id = self.config.get('general', 'sensor_id')
                self.logger.debug('HoneySens Configuration\n Server: {}\n Key file: {}\n Server certificate: {}\n Sensor ID: {}'.format(self.server_name, self.key, self.servercertfile, self.sensor_id))
                ihandler.__init__(self, path)
        except Exception:
            self.logger.debug('Error: Invalid HoneySens configuration, module disabled')
            self.disabled = True

    def handle_incident(self, icd):
        pass

    def handle_incident_dionaea_connection_tcp_accept(self, icd):
        if self.disabled:
            return
        if not icd.con in self.attacks:
            self.attacks[icd.con] = { 'source': icd.con.remote.host, 'messages': [] }
        self.attacks[icd.con]['messages'].append({ 'timestamp': int(time.time()), 'data': 'New TCP connection: {}:{}'.format(icd.con.remote.host, icd.con.remote.port), 'type': 1 })

    def handle_incident_dionaea_connection_tcp_reject(self, icd):
        if self.disabled:
            return
        if not icd.con in self.attacks:
            self.attacks[icd.con] = { 'source': icd.con.remote.host, 'messages': [] }
        self.attacks[icd.con]['messages'].append({ 'timestamp': int(time.time()), 'data': 'TCP connection rejected: {}:{}'.format(icd.con.remote.host, icd.con.remote.port), 'type': 1 })

    def handle_incident_dionaea_download_offer(self, icd):
        if icd.con in self.attacks:
            self.attacks[icd.con]['messages'].append({ 'timestamp': int(time.time()), 'data': 'Download offer: {}'.format(icd.url) })

    def handle_incident_dionaea_download_complete_hash(self, icd):
        if icd.con in self.attacks:
            self.attacks[icd.con]['messages'].append({ 'timestamp': int(time.time()), 'data': 'Download complete: {}, MD5: {}'.format(icd.url, icd.md5hash) })

    def handle_incident_dionaea_service_shell_listen(self, icd):
        if icd.con in self.attacks:
            self.attacks[icd.con]['messages'].append({ 'timestamp': int(time.time()), 'data': 'Shell listening on {}'.format(icd.port) })

    def handle_incident_dionaea_service_shell_connect(self, icd):
        if icd.con in self.attacks:
            self.attacks[icd.con]['messages'].append({ 'timestamp': int(time.time()), 'data': 'Shell connection from {}:{}'.format(icd.host, icd.port) })

    def handle_incident_dionaea_modules_python_smb_dcerpc_request(self, icd):
        if icd.con not in self.attacks:
            return
        try:
            vuln = smb.registered_services[icd.uuid.replace('-', '')].vulns[icd.opnum]
        except:
            vuln = "SMBDialogue"
        self.attacks[icd.con]['messages'].append({ 'timestamp': int(time.time()), 'data': 'DCERPC request, TYPE: {}, UUID: {}, OPNUM: {}'.format(vuln, icd.uuid, icd.opnum) })

    def handle_incident_dionaea_modules_python_smb_dcerpc_bind(self, icd):
        if icd.con in self.attacks:
            self.attacks[icd.con]['messages'].append({ 'timestamp': int(time.time()), 'data': 'DCERPC bind, UUID: {}, TRANSFERSYNTAX: {}'.format(icd.uuid, icd.transfersyntax) })

    def handle_incident_dionaea_connection_free(self, icd):
        if icd.con not in self.attacks:
            return
        self.attacks[icd.con]['messages'].append({ 'timestamp': int(time.time()), 'data': 'Connection Lost', 'type': 1 })
        messages = self.attacks[icd.con]['messages']
        event = [{'timestamp': messages[0]['timestamp'], 'service': 2, 'source': self.attacks[icd.con]['source'], 'summary': 'SMB/CIFS', 'details': messages, 'packets': []}]
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
        del self.attacks[icd.con]
