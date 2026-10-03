#!/usr/bin/env bash
# Local workstation only. PHP packages: https://launchpad.net/~ondrej/+archive/ubuntu/php
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    printf '%s\n' 'Run this script with sudo in your own terminal.' >&2
    exit 1
fi
. /etc/os-release
if [[ ${ID:-} != ubuntu && ${ID:-} != zorin ]] || [[ ${UBUNTU_CODENAME:-${VERSION_CODENAME:-}} != noble ]]; then
    printf '%s\n' 'This installer is scoped to Ubuntu 24.04 / Zorin based on noble.' >&2
    exit 1
fi

apt-get update
apt-get install -y software-properties-common uidmap dbus-user-session
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get install -y php8.4-cli php8.4-curl php8.4-mbstring php8.4-xml \
    php8.4-zip php8.4-pgsql php8.4-intl php8.4-bcmath php8.4-gd unzip
php8.4 --version
printf '%s\n' 'PHP installed. Composer and rootless Docker setup run separately as your normal user.'
