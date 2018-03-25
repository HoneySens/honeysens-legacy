#!/usr/bin/env python2

import beanstalkc
import ConfigParser
import json
import os
import shutil
import subprocess
import sys
import tarfile
import tempfile

if len(sys.argv) != 2:
    print('Usage: honeysens-service-registry-worker.py <appConfig>')
    exit()

config_file = sys.argv[1]
if not os.path.isfile(config_file):
    print('Error: Config file not found')
    exit()

reload(sys)
sys.setdefaultencoding('utf-8')
config = ConfigParser.ConfigParser()
config.readfp(open(config_file))
beanstalk = beanstalkc.Connection(host=config.get('beanstalkd', 'host'), port=int(config.get('beanstalkd', 'port')))
beanstalk.watch('honeysens-service-registry')

upload_path = '{}/data/upload'.format(config.get('server', 'app_path'))
if not os.path.isdir(upload_path):
    print('Error: Server upload directory not found')
    exit()

print('HoneySens Service Registry Worker\n')
print('  Upload directory: {}'.format(upload_path))

while True:
    print('Worker: READY')
    job = beanstalk.reserve()
    try:
        job_data = json.loads(job.body)
    except ValueError:
        print('Error: Invalid input data, removing job')
        job.delete()
        continue
    print('----------------------------------------\nJob received')
    service_archive_path = job_data['archive_path']
    service_name = job_data['name']
    registry_name = 'honeysens-registry:5000/{}'.format(service_name)
    print('Service name: {}\nRegistry tag: {}'.format(service_name, registry_name))
    if not os.path.isfile(service_archive_path):
        print('Error: Archive file not found')
        job.delete()
        continue
    # Create temp directory
    working_dir = tempfile.mkdtemp()
    tar = tarfile.open(service_archive_path)
    tar.extractall(path=working_dir)
    tar.close()
    # Registry interaction
    subprocess.call(['/usr/bin/docker', 'load', '-i', '{}/service.tar'.format(working_dir)])
    print('Adding registry tag reference')
    subprocess.call(['/usr/bin/docker', 'tag', service_name, registry_name])
    print('Pushing image to registry')
    subprocess.call(['/usr/bin/docker', 'push', registry_name])
    print('Cleaning up, removing stale local images')
    subprocess.call(['/usr/bin/docker', 'rmi', service_name, registry_name])
    shutil.rmtree(working_dir)
    os.remove(service_archive_path)
    job.delete()
