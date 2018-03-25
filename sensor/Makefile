default: build/dionaea/src/dionaea

prepare:
	if [ ! -d build ]; then mkdir -p build; fi
	git clone https://github.com/micheloosterhof/cowrie.git build/kippo
	git clone git://github.com/pb-/liblcfg.git build/liblcfg
	git clone http://gitlab.labs.nic.cz/honeynet/dionaea.git build/dionaea

build/liblcfg/code/src/liblcfg.la:
	if [ ! -d build/liblcfg ]; then echo "Error: Please run make prepare first"; return 1; fi
	cd build/liblcfg/code; autoreconf -vi
	cd build/liblcfg/code; ./configure --prefix=/opt/dionaea
	make -C build/liblcfg/code

build/dionaea/src/dionaea: build/liblcfg/code/src/liblcfg.la
	if [ ! -d build/dionaea ]; then echo "Error: Please run make prepare first"; return 1; fi
	cd build/dionaea; autoreconf -vi
	cd build/dionaea; ./configure --with-lcfg-include=$(realpath build/liblcfg/code/include/) --with-lcfg-lib=$(realpath build/liblcfg/code/src/.libs/) --with-python=/usr/bin/python3
	make -C build/dionaea

install: build/dionaea/src/dionaea
	install -d $(DESTDIR)/opt/honeysens $(DESTDIR)/opt/kippo $(DESTDIR)/opt/dionaea
	# kippo
	cp -r build/kippo $(DESTDIR)/opt/
	install -m 0644 src/kippo/honeysens.py $(DESTDIR)/opt/kippo/cowrie/dblog
	install -m 0644 build/kippo/cowrie.cfg.dist $(DESTDIR)/opt/kippo/cowrie.cfg
	cat src/kippo/kippo.cfg >> $(DESTDIR)/opt/kippo/cowrie.cfg
	# dionaea
	make -C build/liblcfg/code install DESTDIR=$(DESTDIR)
	make -C build/dionaea install DESTDIR=$(DESTDIR)
	patch -d $(DESTDIR) -p0 < src/dionaea/dionaea.conf.patch
	patch -d $(DESTDIR) -p0 < src/dionaea/ihandlers.py.patch
	install -m 0644 src/dionaea/honeysens.py $(DESTDIR)/opt/dionaea/lib/dionaea/python/dionaea
	# honeysens
	install -t $(DESTDIR)/opt/honeysens src/honeysens/apply_config.py src/honeysens/config_sensor.py src/honeysens/poll.py src/honeysens/recon.py src/honeysens/utils.py src/honeysens/version.txt
	cp -r src/honeysens/vendor $(DESTDIR)/opt/honeysens

distclean:
	if [ -f build/dionaea/Makefile ]; then make -C build/dionaea clean; fi

clean:
	if [ -d build ]; then rm -rf build; fi

.PHONY: prepare install distclean
