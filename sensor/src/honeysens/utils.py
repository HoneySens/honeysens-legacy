import pycurl
import json
import ConfigParser
import os
from io import BytesIO

REQUEST_TYPE_HEAD = 0
REQUEST_TYPE_GET = 1
REQUEST_TYPE_POST = 2


def perform_https_request(config, path, request_type, verify=True, post_data=None, file_descriptor=None):
    content = BytesIO()
    headers = {}
    c = pycurl.Curl()
    cache = ConfigParser.ConfigParser()

    def parse_headers(header_line):
        if ':' not in header_line:
            return
        name, value = header_line.split(':', 1)
        headers[name.strip().lower()] = value.strip()

    # Request type
    if request_type == REQUEST_TYPE_HEAD:
        c.setopt(pycurl.HTTPGET, 1)
        c.setopt(pycurl.NOBODY, 1)
    elif request_type == REQUEST_TYPE_GET:
        c.setopt(pycurl.HTTPGET, 1)
    elif request_type == REQUEST_TYPE_POST:
        c.setopt(pycurl.POST, 1)
        c.setopt(pycurl.POSTFIELDS, json.dumps(post_data))

    # TLS certificate verification
    if verify:
        c.setopt(pycurl.SSL_VERIFYPEER, 1)
        c.setopt(pycurl.SSL_VERIFYHOST, 2)
    else:
        c.setopt(pycurl.SSL_VERIFYPEER, 0)
        c.setopt(pycurl.SSL_VERIFYHOST, 0)

    # Proxy configuration
    if config.get('proxy', 'mode') == '1':
        c.setopt(pycurl.PROXY, config.get('proxy', 'host'))
        c.setopt(pycurl.PROXYPORT, int(config.get('proxy', 'port')))
        if len(config.get('proxy', 'user')) > 0:
            # Determine authentication scheme
            if not os.path.isfile('cache.cfg'):
                cache.add_section('proxy')
                cache.set('proxy', 'auth_scheme', str(pycurl.HTTPAUTH_ANY))
                with open('cache.cfg', 'wb') as f:
                    cache.write(f)
            else:
                cache.readfp(open('cache.cfg'))
            c.setopt(pycurl.PROXYAUTH, int(cache.get('proxy', 'auth_scheme')))
            c.setopt(pycurl.PROXYUSERPWD, '{}:{}'.format(config.get('proxy', 'user'), config.get('proxy', 'password')))

    # Target output
    if file_descriptor is not None:
        c.setopt(pycurl.WRITEDATA, file_descriptor)
    else:
        c.setopt(pycurl.WRITEFUNCTION, content.write)

    c.setopt(pycurl.URL, 'https://{}:{}/{}'.format(config.get('server', 'name'), config.get('server', 'port_https'), path))
    c.setopt(pycurl.CAINFO, config.get('server', 'certfile'))
    c.setopt(pycurl.HEADERFUNCTION, parse_headers)
    try:
        c.perform()
    except Exception:
        if len(config.get('proxy', 'user')) > 0:
            # Brute-force proxy auth schemes
            success_scheme = None
            for auth_scheme in [pycurl.HTTPAUTH_NTLM, pycurl.HTTPAUTH_GSSNEGOTIATE, pycurl.HTTPAUTH_DIGEST]:
                c.setopt(pycurl.PROXYAUTH, auth_scheme)
                try:
                    headers = {}
                    c.perform()
                    success_scheme = auth_scheme
                    break
                except Exception:
                    pass
            if success_scheme is not None:
                cache.set('proxy', 'auth_scheme', success_scheme)
                with open('cache.cfg', 'wb') as f:
                    cache.write(f)

    status_code = c.getinfo(pycurl.HTTP_CODE)
    c.close()

    return {'status': status_code, 'headers': headers, 'content': content.getvalue()}
