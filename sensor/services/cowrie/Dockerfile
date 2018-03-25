FROM k0st/cowrie
MAINTAINER Pascal Brueckner pascal.brueckner@tu-dresden.de

COPY honeysens.py /home/cowrie/cowrie/cowrie/dblog/
COPY cowrie.cfg /home/cowrie/
USER root
RUN apk --update add gcc g++ python-dev musl-dev zeromq openssh-client
RUN pip install pyzmq
RUN cat /home/cowrie/cowrie.cfg >> /home/cowrie/cowrie/cowrie.cfg
USER cowrie
