#!/usr/bin/env python2

from __future__ import absolute_import

import argparse
import ConfigParser
import os
import shutil
import signal
import sys
import tarfile
import tempfile
import threading
import zmq

from . import collector
from . import commands
from . import event_processor
from . import hooks
from . import polling
from . import services
from . import state
from .utils import constants

manager = None


class Manager:

    config_archive = None
    config = ConfigParser.ConfigParser()
    config_dir = None
    platform = None
    zmq_context = zmq.Context()
    events = {}
    events_lock = threading.Lock()
    services = {}
    #services = {'cowrie': {
        #'container': None,
        #'image': 'honeysens/cowrie:0.1.0'
    #}}

    def __init__(self, config_archive):
        self.config_archive = config_archive
        self.init_config()

    def init_config(self):
        if not os.path.isfile(self.config_archive):
            print('Error: Could not open configuration archive {}'.format(self.config_archive))
            exit()
        # Unpack and parse
        try:
            self.config_dir = tempfile.mkdtemp()
            with tarfile.open(self.config_archive) as config_archive:
                config_archive.extractall(self.config_dir)
            self.config.readfp(open('{}/honeysens.cfg'.format(self.config_dir)))
        except Exception as e:
            print('Error: Could not parse configuration ({})'.format(str(e)))
        print('Configuration from {} initialized'.format(self.config_archive))

    def init_platform(self):
        platform_module_path = os.path.dirname(os.path.abspath(__file__)) + '/platforms/platform.py'
        if os.path.isfile(platform_module_path):
            from .platforms.platform import Platform
            print('Initializing platform module')
            self.platform = Platform(hooks)

    def start(self):
        self.init_platform()
        services.init(self.config_dir, self.config, self.services, hooks)
        hooks.execute_hook(constants.Hooks.ON_INIT)
        # Apply initial configuration
        print('Applying initial configuration')
        state.apply_config(self.config, {}, True)
        # Polling
        polling.start(self.config_dir, self.config, self.config_archive)
        event_processor.start(self.config_dir, self.config, self.events, self.events_lock)
        collector.start(self.zmq_context, self.events, self.events_lock)
        commands.start(self.zmq_context)

    def cleanup(self):
        # Stop threads
        shutil.rmtree(self.config_dir)


def sigint_handler(signal, frame):
    print('Received SIGINT, performing graceful shutdown')
    manager.cleanup()
    sys.exit(0)


def main():
    global manager
    parser = argparse.ArgumentParser()
    parser.add_argument("config", help="Sensor configuration archive")
    args = parser.parse_args()
    # Register SIGINT handler
    signal.signal(signal.SIGINT, sigint_handler)
    manager = Manager(args.config)
    manager.start()


if __name__ == '__main__':
    main()
