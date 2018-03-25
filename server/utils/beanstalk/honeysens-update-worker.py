#!/usr/bin/env python2

import beanstalkc
import ConfigParser
import json
import os
import pymysql
import sys

if len(sys.argv) != 2:
    print('Usage: honeysens-update-worker.py <appConfig>')
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
beanstalk.watch('honeysens-update')

data_path = '{}/data'.format(config.get('server', 'app_path'))
if not os.path.isdir(data_path):
    print('Error: Data directory not found')
    exit()

print('HoneySens Update Worker\n')

while True:
    print('Worker: READY')
    job = beanstalk.reserve()

    # Parse job data
    try:
        job_data = json.loads(job.body)
    except ValueError:
        print('Error: Invalid input data, removing job')
        job.delete()
        continue
    # Reread configuration
    config = ConfigParser.ConfigParser()
    # Preserve the case of keys instead of forcing them lower-case
    config.optionxform = str
    config.readfp(open(config_file))
    # Initiate db connection
    db = pymysql.connect(host=config.get('database', 'host'), port=int(config.get('database', 'port')),
                         user=config.get('database', 'user'), passwd=config.get('database', 'password'),
                         db=config.get('database', 'dbname'))
    server_version = job_data['server_version']
    if config.has_option('server', 'config_version'):
        config_version = config.get('server', 'config_version')
    else:
        # 0.1.5 was the last version without configuration versioning, it's safe to assume this
        config_version = '0.1.5'
        config.set('server', 'config_version', config_version)
    print('----------------------------------------\nJob received')
    print('  Server version: {}'.format(server_version))
    print('  Config version: {}'.format(config_version))

    # Determine if an update is required at all
    if config_version == server_version:
        print('Error: No update necessary')
        job.delete()
        continue

    # Create update marker
    marker_path = '{}/UPDATE'.format(data_path)
    if not os.path.isfile(marker_path):
        print('Creating update marker as {}'.format(marker_path))
        open(marker_path, 'w+')

    # 0.1.5 -> 0.2.0
    if config_version == '0.1.5':
        print('Upgrading configuration: 0.1.5 -> 0.2.0')
        config.set('server', 'debug', 'false')
        config.set('server', 'setup', 'false')
        config.set('server', 'certfile', '/opt/HoneySens/data/ssl-cert.pem')
        config.remove_option('server', 'portHTTP')
        try:
            db.cursor().execute('ALTER TABLE sensors DROP serverEndpointPortHTTP')
            db.cursor().execute("INSERT INTO last_updates(table_name, timestamp) VALUES ('stats', 0)")
            db.commit()
        except Exception:
            pass
        config.set('server', 'config_version', '0.2.0')
        config_version = '0.2.0'
    # 0.2.0 -> 0.2.1
    if config_version == '0.2.0':
        print('Upgrading configuration: 0.2.0 -> 0.2.1')
        config.set('server', 'config_version', '0.2.1')
        config_version = '0.2.1'
    # 0.2.1 -> 0.2.2
    if config_version == '0.2.1':
        print('Upgrading configuration: 0.2.1 -> 0.2.2')
        config.set('server', 'config_version', '0.2.2')
        config_version = '0.2.2'
    # 0.2.2 -> 0.2.3
    if config_version == '0.2.2':
        print('Upgrading configuration: 0.2.2 -> 0.2.3')
        config.set('smtp', 'enabled', 'false')
        config.set('server', 'config_version', '0.2.3')
        config_version = '0.2.3'
    # 0.2.3 -> 0.2.4
    if config_version == '0.2.3':
        print('Upgrading configuration 0.2.3 -> 0.2.4')
        try:
            db.cursor().execute('ALTER TABLE contacts ADD sendAllEvents TINYINT(1) NOT NULL')
            db.cursor().execute('CREATE TABLE service_assignments (id INT AUTO_INCREMENT NOT NULL, sensor_id INT DEFAULT NULL, service_id INT DEFAULT NULL, revision_id INT DEFAULT NULL, INDEX IDX_FC107671A247991F (sensor_id), INDEX IDX_FC107671ED5CA9E6 (service_id), UNIQUE INDEX UNIQ_FC1076711DFA7C8F (revision_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB')
            db.cursor().execute('ALTER TABLE service_assignments ADD CONSTRAINT FK_FC107671A247991F FOREIGN KEY (sensor_id) REFERENCES sensors (id)')
            db.cursor().execute('ALTER TABLE service_assignments ADD CONSTRAINT FK_FC107671ED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id)')
            db.cursor().execute('ALTER TABLE service_assignments ADD CONSTRAINT FK_FC1076711DFA7C8F FOREIGN KEY (revision_id) REFERENCES service_revisions (id)')
            db.cursor().execute('ALTER TABLE services ADD defaultRevision_id INT DEFAULT NULL')
            db.cursor().execute('ALTER TABLE services ADD CONSTRAINT FK_7332E169B00E5743 FOREIGN KEY (defaultRevision_id) REFERENCES service_revisions (id)')
            db.cursor().execute('CREATE UNIQUE INDEX UNIQ_7332E169B00E5743 ON services (defaultRevision_id)')
        except Exception:
            pass
        config.add_section('registry')
        config.set('registry', 'port', '5000')
        config.set('registry', 'host', 'honeysens-registry')
        config.set('server', 'config_version', '0.2.4')
        config_version = '0.2.4'
    # 0.2.4 -> 0.2.5
    if config_version == '0.2.4':
        print('Upgrading configuration 0.2.4 -> 0.2.5')
        config.set('server', 'config_version', '0.2.5')
        config_version = '0.2.5'

    # Write new config file
    config.set('server', 'config_version', server_version)
    with open(sys.argv[1], 'wb') as f:
        config.write(f)

    # Removing update marker
    os.remove(marker_path)

    db.close()
    job.delete()
