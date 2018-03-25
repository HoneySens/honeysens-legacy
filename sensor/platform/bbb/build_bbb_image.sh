#!/bin/bash

if [ ! -f "$1" -o ! -f "$2" -o ! -f "$3" ]; then
  echo "Usage: build_bbb_image.sh <honeysens-sensor-*.deb> <honeysens-sensor-platform-bbb-*.deb> <ssh public key file>"
  exit 1
fi

TIME=$(date +%Y-%m-%d)
IMAGE="bb.org-debian-jessie-console-v4.1"
IMAGE_DIR="debian-8.2-console-armhf"
IMAGE_TAR="armhf-rootfs-debian-jessie"
SENSOR_DEB=$(basename "$1")
PLATFORM_DEB=$(basename "$2")

if [ ! -d ./image-builder ]; then
  git clone https://github.com/beagleboard/image-builder.git
fi

# Bootstrap Debian
cd ./image-builder
./RootStock-NG.sh -c "${IMAGE}"
cd deploy/"${IMAGE_DIR}-${TIME}"
test -d root || mkdir root
sudo tar -xf ${IMAGE_TAR}.tar -C root
sudo cp /usr/bin/qemu-arm-static root/usr/bin/
sudo mount --bind /proc root/proc
sudo mount --bind /sys root/sys
sudo cp "$1" "$2" root/root

# Generic sensor preparation
sudo chroot root locale-gen en_US.UTF-8
sudo chroot root passwd -l debian
sudo chroot root passwd -l root
sudo chroot root echo "HoneySens Sensor Software based on Debian GNU/Linux" > /etc/issue
sudo chroot root mkdir /home/debian/.ssh
sudo cat "$3" >> root/home/debian/.ssh/authorized_keys
sudo chroot root chown -R debian:debian /home/debian/.ssh

# Install HoneySens sensor software
sudo chroot root apt-get update
sudo chroot root dpkg -i /root/${SENSOR_DEB}
sudo chroot root dpkg -i /root/${PLATFORM_DEB}
# TODO consider changing macchanger settings after installation since default randomizes MACs
DEBIAN_FRONTEND=noninteractive sudo chroot root apt-get -yf install
sudo umount root/proc
sudo umount root/sys

# Repack rootfs
echo "Creating new rootfs archive..."
rm ${IMAGE_TAR}.tar
sudo tar -C root -cf ${IMAGE_TAR}.tar .
sudo rm -r ./root
echo "Creating SD card image..."
# TODO patch setup_sdcard.sh with dmsetup mknodes so that it runs in container environments, e.g. lxc
sudo ./setup_sdcard.sh --img-2gb "HoneySens-$IMAGE" --dtb beaglebone --boot_label HONEYSENS --enable-systemd --hostname sensor --beagleboard.org-production --bbb-flasher --bbb-old-bootloader-in-emmc
