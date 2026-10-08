#!/usr/bin/env bash
# Proves the newest backup restores: loads it into a scratch database, compares row counts with the live
# database, checks the files archive lists, then drops the scratch database. Never touches the live data.
# Usage (on the server): deploy/backup/restore-check.sh [path/to/db-backup.sql.gz]
set -euo pipefail

STACK_DIR="$(cd "$(dirname "$0")/../preview" && pwd)"
DEST="${BACKUP_DIR:-$HOME/backups}"
SCRATCH="getsorted_restore_check"
TABLES="users pros pro_documents service_jobs quotes introductions pro_credit_entries reviews notifications"

cd "$STACK_DIR"
DB_FILE="${1:-$(ls -1t "$DEST"/daily/db-*.sql.gz 2>/dev/null | head -1)}"
[ -n "$DB_FILE" ] && [ -f "$DB_FILE" ] || { echo "no database backup found in $DEST/daily" >&2; exit 1; }
FILES_FILE="$(ls -1t "$DEST"/daily/files-*.tar.gz 2>/dev/null | head -1 || true)"

psql_root() { docker compose exec -T pgsql psql -U getsorted -d postgres -v ON_ERROR_STOP=1 -tA "$@"; }
psql_db() { docker compose exec -T pgsql psql -U getsorted -d "$1" -v ON_ERROR_STOP=1 -tA -c "$2"; }

cleanup() { psql_root -c "DROP DATABASE IF EXISTS $SCRATCH" >/dev/null 2>&1 || true; }
trap cleanup EXIT

echo "restoring $(basename "$DB_FILE") into $SCRATCH"
cleanup
psql_root -c "CREATE DATABASE $SCRATCH" >/dev/null
gunzip -c "$DB_FILE" | docker compose exec -T pgsql psql -U getsorted -d "$SCRATCH" -v ON_ERROR_STOP=1 -q >/dev/null

fail=0
printf '%-22s %10s %10s\n' table live restored
for table in $TABLES; do
  live="$(psql_db getsorted "SELECT count(*) FROM $table" 2>/dev/null || echo missing)"
  restored="$(psql_db "$SCRATCH" "SELECT count(*) FROM $table" 2>/dev/null || echo missing)"
  printf '%-22s %10s %10s\n' "$table" "$live" "$restored"
  if [ "$restored" = "missing" ] && [ "$live" != "missing" ]; then fail=1; fi
  # Rows added since the backup are fine; the restored copy must never have more, or none when the live table has some.
  if [ "$live" != "missing" ] && [ "$restored" != "missing" ]; then
    if [ "$restored" -gt "$live" ]; then fail=1; fi
    if [ "$live" -gt 0 ] && [ "$restored" -eq 0 ]; then fail=1; fi
  fi
done

postgis="$(psql_db "$SCRATCH" "SELECT count(*) FROM pg_extension WHERE extname = 'postgis'")"
[ "$postgis" = "1" ] || { echo "PostGIS did not come back with the restore" >&2; fail=1; }

if [ -n "$FILES_FILE" ]; then
  entries="$(tar -tzf "$FILES_FILE" | wc -l | tr -d ' ')"
  echo "files archive $(basename "$FILES_FILE") lists $entries entries"
  [ "$entries" -gt 0 ] || fail=1
else
  echo "no files archive found" >&2
  fail=1
fi

if [ "$fail" -eq 0 ]; then echo "RESTORE CHECK PASSED"; else echo "RESTORE CHECK FAILED" >&2; exit 1; fi
