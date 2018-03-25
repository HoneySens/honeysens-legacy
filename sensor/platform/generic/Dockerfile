# Execute docker build for this Dockerfile from the sensor root directory
FROM gliderlabs/alpine:3.5
MAINTAINER Pascal Brueckner pascal.brueckner@tu-dresden.de

RUN apk --no-cache add py-pip py-curl ca-certificates alpine-sdk python-dev linux-headers docker
# honeyd dependencies
RUN apk --no-cache add libevent-dev libdnet-dev libpcap-dev pcre-dev libedit-dev automake autoconf zlib-dev libtool
COPY manager /opt/manager
COPY platform/generic/init.sh /usr/local/sbin/
COPY platform/generic/prepare.sh platform/generic/install_arpd.sh platform/generic/honeyd.diff platform/generic/arpd.diff /root/
RUN /root/prepare.sh
RUN /root/install_arpd.sh
ENTRYPOINT ["init.sh"]
