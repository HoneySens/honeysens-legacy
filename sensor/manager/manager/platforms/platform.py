from __future__ import absolute_import

from manager.platforms.generic import GenericPlatform
from manager.utils import constants


class Platform(GenericPlatform):

    def __init__(self, hook_mgr):
        print("Initializing platform")
        hook_mgr.register_hook(constants.Hooks.ON_INIT, self.init)
        hook_mgr.register_hook(constants.Hooks.ON_POLL, self.poll)

    def init(self):
        print('Executing INIT hook function')

    def poll(self, config_data):
        print('Executing POLL hook function')
        if 'unhandledEvents' in config_data and config_data['unhandledEvents']:
            # TODO Set LED status in case one is attached
            pass
