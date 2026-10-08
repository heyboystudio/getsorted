#!/usr/bin/env bash
# Nightly backup of the test/staging/production Docker stack: database dump + uploaded files.
# Usage (on the server): deploy/backup/backup.sh     Env: BACKUP_DIR (default ~/backups), BACKUP_S3_URI (optional off-server copy)
set -euo pipefail

STACK_DIR="$(cd "$(dirname "$0")/../preview" && pwd)"
DEST="${BACKUP_DIR:-$HOME/backups}"
STAMP="$(date +%Y%m%d-%H%M%S)"
KEEP_DAILY="${KEEP_DAILY:-14}"
KEEP_WEEKLY="${KEEP_WEEKLY:-8}"

mkdir -p "$DEST/daily" "$DEST/weekly"
cd "$STACK_DIR"

DB_FILE="$DEST/daily/db-$STAMP.sql.gz"
FILES_FILE="$DEST/daily/files-$STAMP.tar.gz"

echo "[$(date -Is)] dumping database"
docker compose exec -T pgsql pg_dump -U getsorted -d getsorted --no-owner --clean --if-exists | gzip -9 > "$DB_FILE.partial"
gzip -t "$DB_FILE.partial"
[ "$(stat -c %s "$DB_FILE.partial" 2>/dev/null || stat -f %z "$DB_FILE.partial")" -gt 1000 ] || { echo "database dump is suspiciously small" >&2; rm -f "$DB_FILE.partial"; exit 1; }
mv "$DB_FILE.partial" "$DB_FILE"

echo "[$(date -Is)] archiving uploaded files"
docker compose exec -T web tar -C /app/storage -czf - app > "$FILES_FILE.partial"
gzip -t "$FILES_FILE.partial"
mv "$FILES_FILE.partial" "$FILES_FILE"

# A weekly copy every Sunday.
if [ "$(date +%u)" = "7" ]; then
  cp "$DB_FILE" "$DEST/weekly/"
  cp "$FILES_FILE" "$DEST/weekly/"
fi

# Keep the newest N of each kind.
prune() { { ls -1t "$1"/$2 2>/dev/null || true; } | tail -n +"$(($3 + 1))" | xargs -r rm -f --; }
prune "$DEST/daily" 'db-*.sql.gz' "$KEEP_DAILY"
prune "$DEST/daily" 'files-*.tar.gz' "$KEEP_DAILY"
prune "$DEST/weekly" 'db-*.sql.gz' "$KEEP_WEEKLY"
prune "$DEST/weekly" 'files-*.tar.gz' "$KEEP_WEEKLY"

# Optional copy to a bucket in another place, because backups on the same disk do not survive losing the server.
if [ -n "${BACKUP_S3_URI:-}" ]; then
  if command -v aws >/dev/null 2>&1; then
    aws s3 cp "$DB_FILE" "$BACKUP_S3_URI/" --only-show-errors
    aws s3 cp "$FILES_FILE" "$BACKUP_S3_URI/" --only-show-errors
    echo "[$(date -Is)] copied to $BACKUP_S3_URI"
  else
    echo "BACKUP_S3_URI is set but the aws command is not installed" >&2
  fi
fi

echo "[$(date -Is)] done: $(basename "$DB_FILE") $(du -h "$DB_FILE" | cut -f1), $(basename "$FILES_FILE") $(du -h "$FILES_FILE" | cut -f1)"
