#!/usr/bin/env bash
# Quality gate against the dedicated local test database, never app DB settings.
set -euo pipefail
sortd_root=$(cd "$(dirname "$0")/.." && pwd)
cd "$sortd_root"
if [[ -f bootstrap/cache/config.php ]]; then
    printf '%s\n' 'Cached Laravel configuration exists. Clear it before local verification.' >&2
    exit 1
fi
export PATH="$HOME/.local/bin:/opt/local/bin:$PATH"
composer check-platform-reqs
sortd_pg=/opt/local/lib/postgresql17/bin
sortd_socket="$HOME/.local/share/getsorted-postgresql17/socket"
sortd_expected="$HOME/.local/share/getsorted-postgresql17/data"
sortd_actual=$("$sortd_pg/psql" -X -P pager=off -h "$sortd_socket" -U "$(id -un)" -d postgres -At -v ON_ERROR_STOP=1 -c 'SHOW data_directory')
if [[ "$sortd_actual" != "$sortd_expected" ]]; then
    printf '%s\n' 'Unexpected database cluster; refusing verification.' >&2
    exit 1
fi
export APP_ENV=testing DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432
export DB_DATABASE=sortd_testing DB_USERNAME=sortd DB_PASSWORD=sortd_local DB_URL=''
PGPASSWORD=sortd_local "$sortd_pg/psql" -X -P pager=off -h 127.0.0.1 -p 5432 -U sortd -d sortd_testing -v ON_ERROR_STOP=1 -c 'SELECT current_database(), version(), PostGIS_Full_Version();'
composer check
npm audit --audit-level=high
npm run build
