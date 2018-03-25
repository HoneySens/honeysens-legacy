# Write interface
from string import Template
import toolutils
import StringIO


class InterfacesWriter:
    ''' Short lived class to write interfaces file '''

    # Define templetes for blocks used in /etc/network/interfaces.
    _auto = Template('auto $name\n')
    _hotplug = Template('allow-hotplug $name\n')
    _iface = Template('iface $name $addrFam $source\n')
    _cmd = Template('\t$varient $value\n')

    _addressFields = ['address', 'network', 'netmask', 'broadcast', 'gateway']
    _prepFields = ['pre-up', 'up', 'down', 'post-down']
    _bridgeFields = ['ports', 'fd', 'hello', 'maxage', 'stp']

    def __init__(self, adapters, interfaces_path, backup_path=None):
        ''' if backup_path is None => no backup '''
        self._adapters = adapters
        self._interfaces_path = interfaces_path
        self._backup_path = backup_path

    @property
    def adapters(self):
        return self._adapters

    @adapters.setter
    def adapters(self, value):
        self._adapters = value

    def write_interfaces(self):
        # Back up the old interfaces file.
        self._backup_interfaces()

        try:
            # Prepare to write the new interfaces file.
            with toolutils.atomic_write(self._interfaces_path) as interfaces:
                self._write_interfaces_to_file(interfaces)
        except:
            # Any error, let's roll back
            self._restore_interfaces()
            raise

    def write_interfaces_as_string(self):
        string_file = StringIO.StringIO()
        self._write_interfaces_to_file(string_file)
        string_file.seek(0)
        return string_file.read()

    def _write_interfaces_to_file(self, fileObj):
        ''' Loop through the provided networkAdaprers and write the new file. '''
        for adapter in self._adapters:
            # Get dict of details about the adapter.
            self._write_adapter(fileObj, adapter)

    def _write_adapter(self, interfaces, adapter):
        try:
            adapter.validateAll()
        except ValueError as e:
            print(e.message)
            raise

        ifAttributes = adapter.export()

        self._write_auto(interfaces, adapter, ifAttributes)
        self._write_hotplug(interfaces, adapter, ifAttributes)
        self._write_addrFam(interfaces, adapter, ifAttributes)
        self._write_addressing(interfaces, adapter, ifAttributes)
        self._write_bridge(interfaces, adapter, ifAttributes)
        self._write_callbacks(interfaces, adapter, ifAttributes)
        self._write_unknown(interfaces, adapter, ifAttributes)
        interfaces.write("\n")

    def _write_auto(self, interfaces, adapter, ifAttributes):
        ''' Write if applicable '''
        try:
            if adapter._ifAttributes['auto'] is True:
                d = dict(name=ifAttributes['name'])
                interfaces.write(self._auto.substitute(d))
        except KeyError:
            pass

    def _write_hotplug(self, interfaces, adapter, ifAttributes):
        ''' Write if applicable '''
        try:
            if ifAttributes['hotplug'] is True:
                d = dict(name=ifAttributes['name'])
                interfaces.write(self._hotplug.substitute(d))
        except KeyError:
            pass

    def _write_addrFam(self, interfaces, adapter, ifAttributes):
        ''' Construct and write the iface declaration.
            The addrFam clause needs a little more processing.
        '''
        # Write the source clause.
        # Will not error if omitted. Maybe not the best plan.
        try:
            d = dict(name=ifAttributes['name'], addrFam=ifAttributes['addrFam'], source=ifAttributes['source'])
            interfaces.write(self._iface.substitute(d))
        except KeyError:
            pass

    def _write_addressing(self, interfaces, adapter, ifAttributes):
        for field in self._addressFields:
            try:
                if ifAttributes[field] and ifAttributes[field] != 'None':
                    d = dict(varient=field, value=ifAttributes[field])
                    interfaces.write(self._cmd.substitute(d))
            # Keep going if a field isn't provided.
            except KeyError:
                pass

    def _write_bridge(self, interfaces, adapter, ifAttributes):
        ''' Write the bridge information. '''
        for field in self._bridgeFields:
            try:
                d = dict(varient="bridge_" + field, value=ifAttributes['bridge-opts'][field])
                interfaces.write(self._cmd.substitute(d))
            # Keep going if a field isn't provided.
            except KeyError:
                pass

    def _write_callbacks(self, interfaces, adapter, ifAttributes):
        ''' Write the up, down, pre-up, and post-down clauses. '''
        for field in self._prepFields:
            for item in ifAttributes[field]:
                try:
                    d = dict(varient=field, value=item)
                    interfaces.write(self._cmd.substitute(d))
                # Keep going if a field isn't provided.
                except KeyError:
                    pass

    def _write_unknown(self, interfaces, adapter, ifAttributes):
        ''' Write unknowns options '''
        try:
            for k, v in ifAttributes['unknown'].iteritems():
                d = dict(varient=k, value=str(v))
                interfaces.write(self._cmd.substitute(d))
        except (KeyError, ValueError):
            pass

    def _backup_interfaces(self):
        ''' return True/False, command output '''

        if self._backup_path:
            return toolutils.safe_subprocess(["cp", self._interfaces_path, self._backup_path])

    def _restore_interfaces(self):
        ''' return True/False, command output '''

        if self._backup_path:
            return toolutils.safe_subprocess(["cp", self._backup_path, self._interfaces_path])
