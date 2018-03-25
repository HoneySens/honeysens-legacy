#!/usr/bin/env python2
# -*- coding: utf-8 -*-

from setuptools import setup, find_packages

setup(
    name='honeysens-manager',
    version='0.3.0',
    description='HoneySens sensor management daemon',
    author='Pascal Brueckner',
    author_email='pascal.brueckner@tu-dresden.de',
    license='BSD',
    packages=find_packages(),
    install_requires=[
        'colorama',
        'docker-py',
        'netifaces',
        'pycrypto',
        'pycurl',
        'pyzmq'
    ],
    entry_points={
        'console_scripts': [
            'manager=manager.manager:main',
            'manager-cli=manager.cli:main'
        ]
    }
)
