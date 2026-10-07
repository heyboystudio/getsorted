#!/usr/bin/env bash
# User-local Node 22 install from the official archive; no sudo or app environment.
set -euo pipefail

if [[ $(uname -s) != Darwin || $(uname -m) != x86_64 ]]; then
    printf '%s\n' 'This installer is for Intel macOS.' >&2
    exit 1
fi

sortd_tmp=$(mktemp -d)
trap 'rm -rf "$sortd_tmp"' EXIT
sortd_dist=https://nodejs.org/dist/latest-v22.x
curl --fail --location --proto '=https' --tlsv1.2 "$sortd_dist/SHASUMS256.txt" -o "$sortd_tmp/SHASUMS256.txt"
sortd_archive=$(awk '$2 ~ /^node-v22\.[0-9]+\.[0-9]+-darwin-x64\.tar\.gz$/ { print $2 }' "$sortd_tmp/SHASUMS256.txt")
if [[ ! $sortd_archive =~ ^node-v22\.[0-9]+\.[0-9]+-darwin-x64\.tar\.gz$ ]]; then
    printf '%s\n' 'Could not identify exactly one official Node 22 Intel archive.' >&2
    exit 1
fi
curl --fail --location --proto '=https' --tlsv1.2 "$sortd_dist/$sortd_archive" -o "$sortd_tmp/$sortd_archive"
awk -v archive="$sortd_archive" '$2 == archive' "$sortd_tmp/SHASUMS256.txt" > "$sortd_tmp/node-checksum.txt"
(cd "$sortd_tmp" && shasum -a 256 -c node-checksum.txt)
tar -xzf "$sortd_tmp/$sortd_archive" -C "$sortd_tmp"
sortd_name=${sortd_archive%.tar.gz}
"$sortd_tmp/$sortd_name/bin/node" -e 'const [major, minor] = process.versions.node.split(".").map(Number); if (major !== 22 || minor < 12) process.exit(1); console.log(process.version)'
mkdir -p "$HOME/.local/share" "$HOME/.local/bin"
if [[ -e "$HOME/.local/share/$sortd_name" ]]; then
    printf '%s\n' 'That Node version is already installed; leaving it untouched.' >&2
    exit 1
fi
for sortd_tool in node npm npx; do
    if [[ -e "$HOME/.local/bin/$sortd_tool" || -L "$HOME/.local/bin/$sortd_tool" ]]; then
        printf 'Existing %s in ~/.local/bin; leaving it untouched.\n' "$sortd_tool" >&2
        exit 1
    fi
done
mv "$sortd_tmp/$sortd_name" "$HOME/.local/share/$sortd_name"
for sortd_tool in node npm npx; do
    ln -s "$HOME/.local/share/$sortd_name/bin/$sortd_tool" "$HOME/.local/bin/$sortd_tool"
done
export PATH="$HOME/.local/bin:$PATH"
node --version
npm --version
printf '%s\n' 'Node installed. For other terminals: export PATH="$HOME/.local/bin:$PATH"'
