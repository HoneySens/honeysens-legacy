#!/usr/bin/env sh
docker daemon &
if [ -z "$CONFIG_FILE" ]; then
  echo "Error: Environment variable CONFIG_FILE is not set"
  exit 1
fi
manager /etc/manager/$CONFIG_FILE