#!/usr/bin/env bash
# Run in the Mac's Terminal after installing MacPorts for Monterey.
set -euo pipefail

if [[ $(uname -s) != Darwin || $(uname -m) != x86_64 ]]; then
    printf '%s\n' 'This setup is for Intel macOS.' >&2
    exit 1
fi
if [[ ! -x /opt/local/bin/port ]]; then
    printf '%s\n' 'Install the Monterey MacPorts package from https://www.macports.org/install.php first.' >&2
    exit 1
fi

sudo /opt/local/bin/port selfupdate
sudo /opt/local/bin/port install php84 php84-curl php84-mbstring php84-intl php84-gd php84-zip php84-openssl php84-exif php84-iconv
sudo /opt/local/bin/port install php84-postgresql +postgresql17
sudo /opt/local/bin/port select --set php php84
export PATH="$HOME/.local/bin:/opt/local/bin:/opt/local/sbin:$PATH"

# Refuse to proceed unless PHP and all of the project's required modules work.
/opt/local/bin/php84 -r 'if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) { exit(1); } foreach (["curl", "mbstring", "dom", "zip", "pdo_pgsql", "pgsql", "intl", "bcmath", "gd", "openssl", "exif", "iconv"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: ".$extension.PHP_EOL); exit(1); } } echo "PHP ".PHP_VERSION." and required extensions: OK".PHP_EOL;'

# Make PHP available through the same user-local PATH as Node and Codex.
mkdir -p "$HOME/.local/bin"
if [[ ! -e "$HOME/.local/bin/php" && ! -L "$HOME/.local/bin/php" ]]; then
    ln -s /opt/local/bin/php84 "$HOME/.local/bin/php"
fi
php -r 'if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) { fwrite(STDERR, "Another PHP version shadows PHP 8.4 on PATH".PHP_EOL); exit(1); }'

if command -v composer >/dev/null 2>&1; then
    composer --version
    printf '%s\n' 'Existing Composer retained.'
else
    sortd_tmp=$(mktemp -d)
    trap 'rm -rf "$sortd_tmp"' EXIT
    curl --fail --location --proto '=https' --tlsv1.2 https://composer.github.io/installer.sig -o "$sortd_tmp/installer.sig"
    curl --fail --location --proto '=https' --tlsv1.2 https://getcomposer.org/installer -o "$sortd_tmp/composer-setup.php"
    php -r '$expected = trim(file_get_contents($argv[1])); if (!preg_match("/^[a-f0-9]{96}$/", $expected) || !hash_equals($expected, hash_file("sha384", $argv[2]))) { fwrite(STDERR, "Composer installer verification failed".PHP_EOL); exit(1); } echo "Composer installer verified".PHP_EOL;' "$sortd_tmp/installer.sig" "$sortd_tmp/composer-setup.php"
    php "$sortd_tmp/composer-setup.php" --2 --install-dir="$HOME/.local/bin" --filename=composer
    composer --version
fi

printf '%s\n' 'PHP and Composer installed. Project dependencies and the database still need verification.'
