# Private test site (preview)

A temporary, password-protected copy of Sortd at https://sortd.heyboy.co.za for clicking through on a phone (decision 037). **Fake data only.** It is not staging and not production.

## What is different there
- `APP_ENV=preview`: WhatsApp, payments, AI and maps are the local Fakes; the login code shows on the screen.
- A "Test site" banner on every page; the whole site needs the shared password; search engines are told not to index it.

## Deploying
From the repo root, on `main`:

```bash
deploy/preview/deploy.sh
```

It builds the assets, copies the code (never `.env`), rebuilds the containers, migrates, and seeds the catalogue and suburbs.

## Server layout
- `ssh aws` (Ubuntu 26.04, EC2 eu-north-1). Code in `~/sortd`. Containers: `web` (FrankenPHP: HTTPS + PHP), `queue`, `scheduler`, `pgsql`.
- Secrets live only in `~/sortd/deploy/preview/.env` on the server: app key, database password and the site password hash. They are never committed or printed.
- Logs: `cd ~/sortd/deploy/preview && docker compose logs -f web`.

## First admin
On the server, run `docker compose exec web php artisan sortd:create-super-admin`. It asks for the details interactively. The founder runs it and types their own details.

## When the server expires
Nothing depends on it. Its database and uploads go with it.
