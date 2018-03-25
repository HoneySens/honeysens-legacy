#!/usr/bin/env bash
apt-get -qq update
# Basic dependencies
apt-get install -y mysql-server beanstalkd screen python python-yaml curl openssl apache2
# PHP 5
add-apt-repository ppa:ondrej/php
apt-get -qq update
apt-get install -y php5.6 php5.6-mbstring php5.6-mysql php5.6-xml libapache2-mod-php5.6
# Beanstalk
sed -i -e 's/#START=yes/START=yes/' -e 's/BEANSTALKD_LISTEN_ADDR=.*/BEANSTALKD_LISTEN_ADDR=127.0.0.1/' /etc/default/beanstalkd
# MySQL
/etc/init.d/mysql start
mysql -u root -e "CREATE DATABASE honeysens"
mysql -u root honeysens -e "GRANT ALL PRIVILEGES ON honeysens.* TO honeysens@localhost IDENTIFIED BY 'honeysens'"
sed -i 's/password.*/password = honeysens/' /opt/HoneySens/data/config.cfg
/etc/init.d/mysql stop
# Apache
chown -R www-data:www-data /opt/HoneySens/cache/ /opt/HoneySens/data/
sed -i -e 's/upload_max_filesize.*/upload_max_filesize = 100M/' -e 's/post_max_size.*/post_max_size = 100M/' /etc/php/5.6/apache2/php.ini
echo www-data > /etc/container_environment/APACHE_RUN_USER
echo www-data > /etc/container_environment/APACHE_RUN_GROUP
echo /var/log/apache2 > /etc/container_environment/APACHE_LOG_DIR
echo /var/lock/apache2 > /etc/container_environment/APACHE_LOCK_DIR
echo /var/run/apache2.pid > /etc/container_environment/APACHE_PID_FILE
echo /var/run/apache2 > /etc/container_environment/APACHE_RUN_DIR
rm /etc/ssl/certs/ssl-cert-snakeoil.pem
rm /etc/ssl/private/ssl-cert-snakeoil.key
a2enmod rewrite
a2enmod ssl
a2enmod headers
a2enmod proxy_http
cp /opt/HoneySens/utils/docker/apache.conf /etc/apache2/sites-enabled/000-default.conf
cp /opt/HoneySens/utils/docker/apache.ssl.conf /etc/apache2/sites-enabled/default-ssl.conf
chmod 755 /var/run/screen # see https://github.com/stucki/docker-cyanogenmod/issues/2
cp /opt/HoneySens/utils/docker/cron.conf /etc/cron.d/honeysens
cp /opt/HoneySens/utils/docker/01_regen_snakeoil_cert.sh /etc/my_init.d/
cp /opt/HoneySens/utils/docker/02_regen_honeysens_ca.sh /etc/my_init.d/
cp /opt/HoneySens/utils/docker/03_fix_permissions.sh /etc/my_init.d/
cp /opt/HoneySens/utils/docker/04_update_mysql.sh /etc/my_init.d/
# Enable SSH
cat /opt/HoneySens/utils/docker/ssh/id_rsa.pub >> /root/.ssh/authorized_keys
rm -rf /opt/HoneySens/utils/docker/ssh
rm -f /etc/service/sshd/down
# Services
cp -r /opt/HoneySens/utils/docker/services/apache2 /etc/service
cp -r /opt/HoneySens/utils/docker/services/mysql /etc/service
cp -r /opt/HoneySens/utils/docker/services/beanstalkd /etc/service
cp -r /opt/HoneySens/utils/docker/services/sensorcfg-creation-worker /etc/service
cp -r /opt/HoneySens/utils/docker/services/update-worker /etc/service
