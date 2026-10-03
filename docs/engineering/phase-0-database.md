# Phase 0 — PostgreSQL + PostGIS as the only database

Scope: roadmap step 3 (decision 002).

## What changed
- `config/database.php` keeps only the `pgsql` connection, defaulting to the `sortd` database and user. Laravel still merges its built-in connection definitions in the background, but nothing selects them.
- Sessions, cache and the queue default to the `database` driver (config and `.env.example`). Failed jobs and job batches use `pgsql`.
- The database queue has `after_commit` enabled, so jobs dispatched inside a transaction are only queued once it commits (conventions: side effects after commit).
- Migration `2026_10_03_000000_enable_postgis_extension` turns PostGIS on. On a managed database the migration user needs permission to create extensions (or PostGIS is pre-enabled); check this when hosting is chosen (decision 014).
- `compose.yaml` runs `postgis/postgis:17-3.5-alpine` locally, published on `127.0.0.1` only, with a named volume. On first start it also creates `sortd_testing`, so tests never touch development data.
- `phpunit.xml` points tests at `sortd_testing` with the local credentials. Tests keep the array cache/session and sync queue for speed; the database drivers are exercised directly in `tests/Feature/DatabaseTest.php`.

## Local use
```bash
docker compose up -d --wait
php artisan migrate
```
`.env` needs `DB_PASSWORD=sortd_local` (from `.env.example`). This password is for the local-only container; staging and production use their own secrets.

## Verified 2026-10-03
- Container healthy: PostgreSQL 17.11 with PostGIS; `sortd` and `sortd_testing` created.
- Pest: 28 passed (49 assertions), including PostGIS distance (Durban City Hall → Umhlanga ≈ 15.9 km), database cache, database queue and Postgres version checks.
- `pint --test` passed; `composer audit` found no advisories.
- Migrations ran on the local development database; a request to `/` returned 200 and stored its session in Postgres.
