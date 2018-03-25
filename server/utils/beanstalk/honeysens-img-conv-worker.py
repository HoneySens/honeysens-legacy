#!/usr/bin/env python2
import sys, beanstalkc, json, tempfile, shutil, subprocess, ConfigParser, pymysql, time, os, re
if(len(sys.argv) != 2):
  print('Usage: honeysens-img-conv-worker.py <configPath>')
  exit()
config = ConfigParser.ConfigParser()
config.read(sys.argv[1])
beanstalk = beanstalkc.Connection(host=config.get('beanstalkd', 'host'), port=int(config.get('beanstalkd', 'port')))
beanstalk.watch('honeysens-imgconv')

while True:
  print('SD conversion worker ready')
  job = beanstalk.reserve()
  jobData = json.loads(job.body)
  print('New job: SD conversion of {}'.format(jobData['fwPath']))
  print('Updating Database')
  conn = pymysql.connect(host=config.get('database', 'host'), port=int(config.get('database', 'port')), user=config.get('database', 'user'), passwd=config.get('database', 'password'), db=config.get('database', 'dbname'))
  cur = conn.cursor()
  cur.execute('SELECT conversionStatus FROM images WHERE id = {}'.format(jobData['fwID']))
  status, = cur.fetchone()
  if status != 1:
    print('Error: Firmware image is not scheduled for conversion')
    job.delete()
    continue
  cur.execute('UPDATE images SET conversionStatus = {} WHERE id = {}'.format(2, jobData['fwID']))
  cur.execute('UPDATE last_updates SET timestamp = NOW() WHERE table_name = "images"')
  conn.commit()
  print('Extracting firmware')
  temp = tempfile.mkdtemp()
  p = subprocess.call(['/bin/tar', 'xzf', jobData['fwPath'], '--directory', temp])
  if p != 0:
    print('Error: Couldn\'t unpack archive.')
    job.delete()
    continue
  print('Preparing empty image')
  subprocess.call(['/bin/dd', 'if=/dev/zero', 'of={}/sd.img'.format(temp), 'bs=1M', 'count=1500'])
  sfdiskInstructions = """
1,96,0xe,*
,,,-
"""
  p = subprocess.Popen(['/sbin/sfdisk', '--force', '--in-order', '--Linux', '--unit', 'M', '{}/sd.img'.format(temp)], stdin=subprocess.PIPE, stdout=subprocess.PIPE)
  p.communicate(sfdiskInstructions)
  p = subprocess.Popen(['/sbin/kpartx', '-av', '{}/sd.img'.format(temp)], stdin=subprocess.PIPE, stdout=subprocess.PIPE)
  out, err = p.communicate()
  lines = out.decode('utf-8').split('\n')
  partitionOne = str(lines[0].split()[2])
  partitionTwo = str(lines[1].split()[2])
  manualDeviceNodes = False
  for p in (partitionOne, partitionTwo):
    blockDevice = '/dev/mapper/{}'.format(p)
    if not os.path.isfile(blockDevice):
      manualDeviceNodes = True
      print('Manually creating device node {}'.format(blockDevice))
      subprocess.call(['/sbin/dmsetup', 'mknodes', p])
  print('Writing SD image')
  subprocess.call(['/bin/dd', 'if={}/boot.img'.format(temp), 'of=/dev/mapper/{}'.format(partitionOne), 'bs=4M'])
  subprocess.call(['/bin/dd', 'if={}/root.img'.format(temp), 'of=/dev/mapper/{}'.format(partitionTwo), 'bs=4M'])
  print('Cleaning up')
  subprocess.call(['/bin/sync'])
  subprocess.call(['/sbin/kpartx', '-dv', '{}/sd.img'.format(temp)])
  if manualDeviceNodes:
      print('Manually removing device nodes')
      subprocess.call(['/sbin/dmsetup', 'mknodes'])
  subprocess.call(['/bin/mv', '{}/sd.img'.format(temp), jobData['outPath']])
  shutil.rmtree(temp)
  cur.execute('UPDATE images SET conversionStatus = {} WHERE id = {}'.format(3, jobData['fwID']))
  cur.execute('UPDATE last_updates SET timestamp = NOW() WHERE table_name = "images"')
  conn.commit()
  cur.close()
  conn.close()
  job.delete()
