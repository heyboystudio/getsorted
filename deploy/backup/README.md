# Backups

Scripts for the Docker stack in `deploy/preview` (the test site now; staging and production use the same layout, or RDS snapshots on AWS, see `docs/engineering/production-move.md`). Run on the server.

| Script | What it does |
|---|---|
| `backup.sh` | Dumps the database (`pg_dump`, gzip) and archives the uploaded files (`storage/app`: vetting documents, photos) to `~/backups/daily`. Sunday copies go to `weekly`. Keeps 14 daily and 8 weekly. Checks each file is a valid gzip. Optional off-server copy: set `BACKUP_S3_URI=s3://bucket/path` and install the `aws` command. |
| `restore-check.sh` | Restores the newest dump into a scratch database, compares row counts with the live database, checks PostGIS came back and the files archive lists, then deletes the scratch database. Exits non-zero on any problem. |

## Schedule (server crontab)
```
30 2 * * * cd $HOME/getsorted/deploy/backup && ./backup.sh >> $HOME/backups/backup.log 2>&1
0 4 * * 0  cd $HOME/getsorted/deploy/backup && ./restore-check.sh >> $HOME/backups/restore-check.log 2>&1
```

## Restoring for real
1. Stop the app containers (`docker compose stop web queue scheduler`).
2. `gunzip -c ~/backups/daily/db-YYYYMMDD-HHMMSS.sql.gz | docker compose exec -T pgsql psql -U getsorted -d getsorted` (the dump drops and recreates objects).
3. Files: `docker compose exec -T web tar -C /app/storage -xzf - < ~/backups/daily/files-YYYYMMDD-HHMMSS.tar.gz`.
4. Start the containers and run `php artisan migrate --force`.

## Limits to know
- Backups on the same disk do not survive losing the server. Set `BACKUP_S3_URI` once a bucket exists.
- The `.env` file is **not** backed up here (it holds secrets). Keep a copy in the founder's password manager.
- A nightly dump is up to 24 hours old. Point-in-time recovery needs RDS or WAL archiving (production plan).
