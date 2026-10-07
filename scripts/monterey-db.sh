#!/usr/bin/env bash
# Dedicated local development cluster; never loads application .env files.
set -euo pipefail
sortd_pg=/opt/local/lib/postgresql17/bin
sortd_base="$HOME/.local/share/getsorted-postgresql17"
sortd_data="$sortd_base/data"
sortd_socket="$sortd_base/socket"
sortd_action=${1:-status}

if [[ ! -x "$sortd_pg/pg_ctl" ]]; then
    printf '%s\n' 'PostgreSQL 17 is not installed; run install-db-monterey.sh first.' >&2
    exit 1
fi
if [[ ! -f "$sortd_data/PG_VERSION" || $(cat "$sortd_data/PG_VERSION") != 17 ]]; then
    printf '%s\n' 'The dedicated GetSorted PostgreSQL 17 cluster is not initialized.' >&2
    exit 1
fi
case "$sortd_action" in
    start)
        if "$sortd_pg/pg_ctl" -D "$sortd_data" status >/dev/null 2>&1; then
            printf '%s\n' 'GetSorted database is already running.'
        else
            "$sortd_pg/pg_ctl" -D "$sortd_data" -l "$sortd_base/server.log" -w start
        fi
        ;;
    stop) "$sortd_pg/pg_ctl" -D "$sortd_data" -m fast -w stop ;;
    status) "$sortd_pg/pg_ctl" -D "$sortd_data" status ;;
    *) printf '%s\n' 'Usage: bash scripts/monterey-db.sh start|stop|status' >&2; exit 1 ;;
esac
