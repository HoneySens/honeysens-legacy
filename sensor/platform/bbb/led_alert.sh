#!/usr/bin/env bash
if [ ! -d /sys/class/gpio/gpio26 ]; then
  echo 26 > /sys/class/gpio/export
fi
if [ ! -d /sys/class/gpio/gpio60 ]; then
  echo 60 > /sys/class/gpio/export
fi
sleep 1
echo high > /sys/class/gpio/gpio60/direction
echo low > /sys/class/gpio/gpio26/direction