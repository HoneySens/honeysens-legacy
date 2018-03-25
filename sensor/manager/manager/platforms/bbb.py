from __future__ import absolute_import

import subprocess

from manager.platforms.generic import GenericPlatform
from manager.utils import constants
from manager.vendor.debinterface import interfaces


class BBB(GenericPlatform):
    def __init__(self, hook_mgr):
        print('Initializing platform module: BeagleBone Black')
        hook_mgr.register_hook(constants.Hooks.ON_APPLY_CONFIG, self.apply_config)

    def apply_config(self, config, server_response, reset_network):
        nw_iface = config.get('network', 'interface')
        if reset_network:
            # Disable all network interfaces
            self.stop_systemd_unit('networking')
            # Update interface definition (/etc/network/interfaces)
            self.update_iface_configuration(nw_iface, config.get('network', 'mode'),
                                            address=config.get('network', 'address'),
                                            netmask=config.get('network', 'netmask'),
                                            gateway=config.get('network', 'gateway'),
                                            dns=config.get('network', 'dns'))
            # Change MAC address if required
            if config.get('mac', 'mode') == '1':
                self.update_mac_address(nw_iface, config.get('mac', 'address'))
            # Restart network interfaces
            self.start_systemd_unit('networking')

    def start_systemd_unit(self, unit):
        subprocess.call(['systemctl', 'start', unit])

    def stop_systemd_unit(self, unit):
        subprocess.call(['systemctl', 'stop', unit])

    def update_mac_address(self, iface, mac):
        print('Changing MAC address of {} to {}'.format(iface, mac))
        subprocess.call(['/usr/bin/macchanger', '-m', mac, iface])

    def update_iface_configuration(self, iface, mode, address=None, netmask=None, gateway=None, dns=None):
        ifaces = interfaces.Interfaces()
        # Verify network interface presence
        if ifaces.getAdapter(iface) is None:
            ifaces.addAdapter(iface, 0)
        adapter = ifaces.getAdapter(iface)
        adapter.setAddrFam('inet')
        # Configure interface details
        if mode == '0':
            adapter.setAddressSource('dhcp')
            adapter.setAddress(None)
            adapter.setNetmask(None)
            adapter.setGateway(None)
            # Debinterfaces is missing the option to remove 'unknown' attributes, therefore we need to improvise
            if 'unknown' in adapter._ifAttributes:
                del (adapter._ifAttributes['unknown'])
        elif mode == '1':
            adapter.setAddressSource('static')
            adapter.setAddress(address)
            adapter.setNetmask(netmask)
            if gateway:
                adapter.setGateway(gateway)
            else:
                adapter.setGateway(None)
            if dns:
                adapter.setUnknown('dns-nameservers', dns)
            else:
                # Debinterfaces is missing the option to remove 'unknown' attributes, therefore we need to improvise
                if 'unknown' in adapter._ifAttributes:
                    del (adapter._ifAttributes['unknown'])
        ifaces.writeInterfaces()
