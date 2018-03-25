from __future__ import absolute_import

import docker
import netifaces
import os
import shutil

from .utils import constants


_config_dir = None
_config = None
_docker = None
_services = {}  # service_name -> {'container': container ID || None, 'image': image}


# TODO Use docker through a proxy with env HTTPS_PROXY

def init(config_dir, config, services, hook_mgr):
    global _config_dir, _config, _docker, _services
    try:
        _config_dir = config_dir
        _config = config
        _docker = docker.Client(base_url=constants.DOCKER_SOCKET, version='auto')
        _services = services
    except docker.errors.DockerException:
        print('Warning: Can\'t access docker daemon, no services will be available')
    hook_mgr.register_hook(constants.Hooks.ON_APPLY_CONFIG, register_registry_cert)
    hook_mgr.register_hook(constants.Hooks.ON_APPLY_CONFIG, apply_services)


def start(service):
    if service not in _services:
        raise Exception('Unknown service {}'.format(service))
    containers = _docker.containers(all=True)
    # Determine docker bridge IP
    collector_host = netifaces.ifaddresses(constants.DOCKER_BRIDGE)[2][0]['addr']
    # Remove known stale container if necessary
    if _services[service]['container'] is not None and _services[service]['container'].get('Image') != _services[service]['image']:
        destroy(service)
        _services[service]['image'] = None
    if _services[service]['container'] is None:
        # Search for existing container with the appropriate name, otherwise create a new one
        container = None
        for c in containers:
            if '/' + service in c.get('Names'):
                # Destroy incompatible containers
                if c.get('Image') != _services[service]['image']:
                    destroy(service)
                else:
                    container = c
        if container is None:
            # Check image availability
            _docker.pull('{}:{}/{}'.format(_config.get('server', 'name'), _config.get('server', 'port_https'), _services[service]['image']))
            # Create new container
            container = _docker.create_container(image='{}:{}/{}'.format(_config.get('server', 'name'), _config.get('server', 'port_https'), _services[service]['image']), name=service, environment={
                'COLLECTOR_HOST': collector_host,
                'COLLECTOR_PORT': constants.COLLECTOR_PORT})
        _services[service]['container'] = container.get('Id')
    # Ensure that service container really exists
    containers = _docker.containers(all=True)
    cid = _services[service]['container']
    if cid not in [c['Id'] for c in containers]:
        # TODO handle that somehow
        raise Exception('Stale container ID {}'.format(cid))
    # Ensure that service container isn't running already
    if cid not in get_running_services():
        _docker.start(container=cid)


def stop(service):
    if service not in _services:
        raise Exception('Unknown service {}'.format(service))
    cid = _services[service]['container']
    if cid in get_running_services():
        _docker.stop(cid)


def destroy(service):
    containers = _docker.containers(all=True)
    for c in containers:
        if '/' + service in c.get('Names'):
            cid = c.get('Id')
            _docker.stop(cid)
            _docker.remove_container(cid)


def stop_all():
    for s in _services:
        stop(s)


def get_running_services():
    return [c['Id'] for c in _docker.containers()]


def apply_services(config, server_response, reset_network):
    global _services
    if 'services' in server_response:
        service_assignments = server_response['services']
        for assignment in service_assignments:
            service_name = assignment['service']['name']
            service_image = assignment['service']['image']
            # Add/update
            if service_name in _services:
                _services[service_name]['image'] = service_image
            else:
                _services[service_name] = {'image': service_image, 'container': None}
            start(service_name)
        # Delete
        for candidate in (set(_services.keys()) - set([sa['service']['name'] for sa in service_assignments])):
            destroy(candidate)
            _services.pop(candidate)


def register_registry_cert(config, server_response, reset_network):
    # Make registry certificate available for the docker client
    docker_config_path = '/etc/docker/certs.d'
    if os.path.isdir(docker_config_path):
        server_cert_path = '{}/{}:{}'.format(docker_config_path, _config.get('server', 'name'), _config.get('server', 'port_https'))
        if not os.path.isdir(server_cert_path):
            os.makedirs(server_cert_path)
        shutil.copy('{}/{}'.format(_config_dir, _config.get('server', 'certfile')), '{}/ca.crt'.format(server_cert_path))
