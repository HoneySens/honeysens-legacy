SW_VERSION = '0.3.0'
CMD_SOCKET = 'tcp://127.0.0.1:5555'
DOCKER_SOCKET = 'unix://var/run/docker.sock'
DOCKER_BRIDGE = 'docker0'
COLLECTOR_PORT = '5556'


class Hooks:
    ON_INIT = 0  # cb()
    ON_POLL = 1  # cb(config_data)
    ON_APPLY_CONFIG = 2  # cb(config, reset_network)
