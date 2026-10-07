# Private test site (preview)

A temporary, publicly reachable copy of Sortd at https://sortd.heyboy.co.za for clicking through on a phone (decision 037). **Fake data only.** It is not staging and not production.

## What is different there
- `APP_ENV=preview`: WhatsApp, payments, AI and maps are the local Fakes; the login code shows on the screen.
- No site password since 2026-10-05 (decision 042); search engines are told not to index it. The founder removed the persistent test-site banner on 2026-10-05; preview still uses fake service providers and test data.

## Deploying
From the repo root, on `main`:

```bash
deploy/preview/deploy.sh
```

It builds the assets, copies the code (never `.env`), rebuilds the containers, migrates, and seeds the catalogue and suburbs.

## Speed settings
`deploy/preview/Caddyfile` compresses responses (zstd/gzip), lets browsers keep the hashed files in `/build/assets/` for a year without re-checking, and keeps `/images/*` for a week. Rename an image (e.g. `-v3`) when you change it. The server is in Stockholm (eu-north-1), about 200 ms away from Durban per round trip.

## Server layout
- `ssh aws` (Ubuntu 26.04, EC2 eu-north-1). Code in `~/sortd`. Containers: `web` (FrankenPHP: HTTPS + PHP), `queue`, `scheduler`, `pgsql`.
- Secrets live only in `~/sortd/deploy/preview/.env` on the server: app key, database password and API keys. They are never committed or printed.
- Logs: `cd ~/sortd/deploy/preview && docker compose logs -f web`.

## First admin
On the server, run `docker compose exec web php artisan sortd:create-super-admin`. It asks for the details interactively. The founder runs it and types their own details.

## When the server expires
Nothing depends on it. Its database and uploads go with it.

## Public website pages
The preview includes a multi-page public website: home, customer guide, trade directory and trade detail pages, pro guide, about, and the legal pages. Sign-up and sign-in use the application routes, including Google and pro registration. The homepage has a "What needs sorting?" box that starts the Siya booking thread with the typed description (decision 048). Terms, privacy and pro agreement are visibly marked as drafts and require legal review before a live launch.

The website's logo and photographs are generated visual assets stored in `public/images/`. They are illustrative and do not depict actual Sortd customers, pros or completed jobs.

The public navigation includes Home, About, Customers, Pros and Contact. The Contact page routes visitors to the customer or pro journey and displays founder-approved placeholder contact details: hello@sortd.heyboy.co.za and 031 000 0000. The subdomain has no receiving MX record yet; replace these details when real contact channels are connected.
