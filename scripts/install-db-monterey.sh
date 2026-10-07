#!/usr/bin/env bash
# Run from Terminal: installs packages, then creates a user-owned local cluster.
set -euo pipefail
sortd_script_dir=$(cd "$(dirname "$0")" && pwd)
if [[ $(uname -s) != Darwin || $(uname -m) != x86_64 || ! -x /opt/local/bin/port ]]; then
    printf '%s\n' 'Intel macOS with MacPorts is required.' >&2
    exit 1
fi
sudo /opt/local/bin/port install php84-exif php84-iconv postgresql17
# Keep defaults: PostGIS 3.6.4's utility build references raster/topology files
# even when those features are disabled, breaking the reduced source build.
sudo /opt/local/bin/port install pg17-postgis +raster +topology
sortd_pg=/opt/local/lib/postgresql17/bin
sortd_base="$HOME/.local/share/getsorted-postgresql17"
sortd_data="$sortd_base/data"
sortd_socket="$sortd_base/socket"
umask 077
mkdir -p "$sortd_base" "$sortd_socket"
if [[ ! -f "$sortd_data/PG_VERSION" ]]; then
    if [[ -e "$sortd_data" ]]; then
        printf '%s\n' 'Existing unrecognized database directory; leaving it untouched.' >&2
        exit 1
    fi
    # Peer authentication protects the owner-only Unix socket; TCP needs a password.
    "$sortd_pg/initdb" -D "$sortd_data" --encoding=UTF8 --locale=C --auth-local=peer --auth-host=scram-sha-256
    cat >> "$sortd_data/postgresql.conf" <<EOF

# GetSorted local-only development settings.
listen_addresses = '127.0.0.1'
port = 5432
unix_socket_directories = '$sortd_socket'
unix_socket_permissions = 0700
EOF
fi
bash "$sortd_script_dir/monterey-db.sh" start
# Every connection below explicitly targets our owned socket, never app settings.
"$sortd_pg/psql" -X -P pager=off -h "$sortd_socket" -U "$(id -un)" -d postgres -v ON_ERROR_STOP=1 <<'SQL'
-- Local development only: superuser is needed for PostGIS recreation in tests,
-- matching the role supplied by the project's existing Docker database.
SELECT 'CREATE ROLE sortd LOGIN SUPERUSER PASSWORD ''sortd_local'''
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'sortd')\gexec
SELECT 'CREATE DATABASE sortd OWNER sortd'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'sortd')\gexec
SELECT 'CREATE DATABASE sortd_testing OWNER sortd'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'sortd_testing')\gexec
SQL
for sortd_db in sortd sortd_testing; do
    "$sortd_pg/psql" -X -P pager=off -h "$sortd_socket" -U "$(id -un)" -d "$sortd_db" -v ON_ERROR_STOP=1 -c 'CREATE EXTENSION IF NOT EXISTS postgis; SELECT PostGIS_Full_Version();'
done
# This is the project's documented local-only credential, not a secret key.
PGPASSWORD=sortd_local "$sortd_pg/psql" -X -P pager=off -h 127.0.0.1 -p 5432 -U sortd -d sortd_testing -v ON_ERROR_STOP=1 -c 'SELECT current_database(), version();'
printf '%s\n' 'Local PostgreSQL 17 + PostGIS ready. Development: sortd. Tests: sortd_testing.'
printf '%s\n' 'Daily commands: bash scripts/monterey-db.sh start  /  bash scripts/monterey-db.sh stop'
