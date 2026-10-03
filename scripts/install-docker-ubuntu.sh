#!/usr/bin/env bash
# Local workstation only. Based on https://docs.docker.com/engine/install/ubuntu/
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    printf '%s\n' 'Run this script with sudo in your own terminal.' >&2
    exit 1
fi

. /etc/os-release
sortd_suite=${UBUNTU_CODENAME:-${VERSION_CODENAME:-}}
if [[ ${ID:-} != ubuntu && ${ID:-} != zorin ]] || [[ $sortd_suite != noble ]]; then
    printf '%s\n' 'This installer is scoped to Ubuntu 24.04 / Zorin based on noble.' >&2
    exit 1
fi

for sortd_package in docker.io docker-compose docker-compose-v2 docker-doc docker-buildx podman-docker containerd runc; do
    if [[ $(dpkg-query -W -f='${Status}' "$sortd_package" 2>/dev/null || true) == 'install ok installed' ]]; then
        printf 'Existing package %s needs review before installing Docker CE. No packages removed.\n' "$sortd_package" >&2
        exit 1
    fi
done

sortd_tmp=$(mktemp -d)
trap 'rm -rf -- "$sortd_tmp"' EXIT
cat > "$sortd_tmp/docker.sources" <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $sortd_suite
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

if [[ -e /etc/apt/sources.list.d/docker.sources ]] && ! cmp -s "$sortd_tmp/docker.sources" /etc/apt/sources.list.d/docker.sources; then
    printf '%s\n' 'An existing Docker source differs; review it before continuing.' >&2
    exit 1
fi
if [[ -e /etc/apt/sources.list.d/docker.list ]]; then
    printf '%s\n' 'An existing docker.list needs review before adding a source.' >&2
    exit 1
fi

apt-get update
apt-get install -y ca-certificates curl
install -m 0755 -d /etc/apt/keyrings
curl --fail --silent --show-error --location --retry 3 --retry-all-errors \
    --connect-timeout 15 --proto '=https' --tlsv1.2 \
    https://download.docker.com/linux/ubuntu/gpg -o "$sortd_tmp/docker.asc"
install -m 0644 "$sortd_tmp/docker.asc" /etc/apt/keyrings/docker.asc
install -m 0644 "$sortd_tmp/docker.sources" /etc/apt/sources.list.d/docker.sources
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin docker-ce-rootless-extras
systemctl start docker
docker version
docker compose version
docker run --rm hello-world

printf '%s\n' 'Docker installed and checked. User access and remaining Phase 0 tools still need configuration.'
