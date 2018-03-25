default: prepare

prepare:
	if [ ! -d build ]; then mkdir -p build; fi
	wget -qO- https://github.com/micheloosterhof/cowrie/archive/v1.1.0.tar.gz | tar xvz -C build
	mv build/cowrie-1.1.0 build/cowrie

install:
	install -d $(DESTDIR)/opt/honeysens $(DESTDIR)/opt/kippo
	# kippo
	cp -r build/kippo $(DESTDIR)/opt/
	install -m 0644 src/kippo/honeysens.py $(DESTDIR)/opt/kippo/cowrie/dblog
	install -m 0644 build/kippo/cowrie.cfg.dist $(DESTDIR)/opt/kippo/cowrie.cfg
	cat src/kippo/kippo.cfg >> $(DESTDIR)/opt/kippo/cowrie.cfg
	# honeysens
	install -t $(DESTDIR)/opt/honeysens src/honeysens/apply_config.py src/honeysens/config_sensor.py src/honeysens/poll.py src/honeysens/recon.py src/honeysens/utils.py src/honeysens/version.txt
	cp -r src/honeysens/vendor $(DESTDIR)/opt/honeysens

clean:
	if [ -d build ]; then rm -rf build; fi

.PHONY: prepare install
