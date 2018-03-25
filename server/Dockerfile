FROM phusion/baseimage:0.9.22

MAINTAINER Pascal Brueckner <pascal.brueckner@tu-dresden.de>

ENV DEBIAN_FRONTEND noninteractive

ADD dist /opt/HoneySens/
RUN /bin/bash /opt/HoneySens/utils/docker/setup.sh

VOLUME /opt/HoneySens/data
VOLUME /var/lib/mysql
EXPOSE 22 80 443

CMD ["/sbin/my_init"]
