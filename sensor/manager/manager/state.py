from __future__ import absolute_import

import fileinput

from . import hooks
from . import services
from .utils import constants


def is_ip(value):
    parts = value.split('.')
    if len(parts) == 4 and all([x.isdigit() for x in parts]):
        numbers = list(int(x) for x in parts)
        return all([num >= 0 and num <= 255 for num in numbers])
    return False


def update_server_endpoint(host, name):
    print('Updating server endpoint in /etc/hosts')
    if 'honeysens-server' in open('/etc/hosts').read():
        # Adjust existing hosts entry
        f = fileinput.input('/etc/hosts', inplace=1)
        for line in f:
            if 'honeysens-server' in line and (host not in line or name not in line):
                print '{}\t{} {}'.format(host, name, 'honeysens-server')
            else:
                print line,
        f.close()
    else:
        # Add new hosts entry
        with open('/etc/hosts', 'a') as f:
            f.write('{}\t{} {}\n'.format(host, name, 'honeysens-server'))


def apply_config(config, server_response, reset_network=False):
    if reset_network:
        # Stop services
        services.stop_all()
        # Adjust /etc/hosts if necessary
        server_host = config.get('server', 'host')
        server_name = config.get('server', 'name')
        if is_ip(server_host):
            update_server_endpoint(server_host, server_name)
    hooks.execute_hook(constants.Hooks.ON_APPLY_CONFIG, [config, server_response, reset_network])

    # Restart required services
    if config.getboolean('services', 'recon'):
        # services.start('recon')
        pass
    if config.getboolean('services', 'kippo'):
        # services.start('cowrie')
        pass
    if config.getboolean('services', 'dionaea'):
        # services.start('dionaea')
        pass

    # Firmware update - call update hook
