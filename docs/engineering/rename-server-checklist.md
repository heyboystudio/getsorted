# Rename on the test server: Sortd → GetSorted

Decision 057. The code and the local Mac are already renamed. These steps rename the **test server** (`ssh aws`, currently `~/sortd`). Run them in order, **before** deploying any code from `chore/rename-getsorted` or later. The server holds fake data only, but these steps keep it anyway.

Time: about 15 minutes. Downtime: about 5 minutes.

## Before you start

- [ ] PR #61 (`feat/auth-v3`) and the rename PR are merged into `main`.
- [ ] You can `ssh aws`.
- [ ] Keep the server's `DB_PASSWORD` handy: `grep DB_PASSWORD ~/sortd/deploy/preview/.env`.

## 1. Stop the app, keep the database running

```bash
ssh aws
cd ~/sortd/deploy/preview
docker compose stop web queue scheduler
```

## 2. Rename the database and its user inside the container

Postgres cannot rename the user you are connected as, so a temporary admin user does it and is then removed. Inside the container, local connections need no password.

```bash
docker compose exec -T pgsql psql -U sortd -d postgres -c "CREATE ROLE gs_tmp SUPERUSER LOGIN"
docker compose exec -T pgsql psql -U gs_tmp -d postgres -c "ALTER DATABASE sortd RENAME TO getsorted"
docker compose exec -T pgsql psql -U gs_tmp -d postgres -c "ALTER ROLE sortd RENAME TO getsorted"
docker compose exec -T pgsql psql -U gs_tmp -d postgres -c "ALTER ROLE getsorted PASSWORD '<the DB_PASSWORD from .env>'"
docker compose exec -T pgsql psql -U getsorted -d postgres -c "DROP ROLE gs_tmp"
docker compose exec -T pgsql psql -U getsorted -d getsorted -c "select count(*) from users"   # should print a number
```

Then stop everything (the data volumes stay; their names come from the `preview` folder, which does not change):

```bash
docker compose down
```

## 3. Rename the folder and the environment file

```bash
cd ~ && mv sortd getsorted
cd ~/getsorted/deploy/preview
cp .env .env.before-rename
sed -i 's/^SORTD_/GETSORTED_/; s/^APP_NAME=.*/APP_NAME=GetSorted/; s/^DB_DATABASE=sortd$/DB_DATABASE=getsorted/; s/^DB_USERNAME=sortd$/DB_USERNAME=getsorted/' .env
grep -n -i sortd .env   # only MAIL_FROM_ADDRESS (and any sortd.heyboy.co.za host) should remain
crontab -l 2>/dev/null | grep -i sortd   # should print nothing; the scheduler runs inside Docker
```

Leave `MAIL_FROM_ADDRESS` on `@sortd.heyboy.co.za` until a new sender domain is verified in Resend (step 6).

## 4. Deploy from your Mac

```bash
cd ~/Documents/getsorted/getsorted
git checkout main && git pull
deploy/preview/deploy.sh        # now copies to ~/getsorted on the server
```

## 5. Check it works

- [ ] https://usesorted.co.za loads; the tab title says **GetSorted**.
- [ ] https://dashboard.usesorted.co.za: admin sign-in works.
- [ ] Sign in as a test customer and open a job (you will be signed out once; that is expected).
- [ ] `ssh aws 'cd ~/getsorted/deploy/preview && docker compose exec -T web php artisan getsorted:send-test-notification --help'` runs.
- [ ] Clean up: `ssh aws 'docker image rm sortd-preview:latest'` (the new image is `getsorted-preview:latest`).

## 6. Later (no rush, no downtime)

- [ ] **Mail sender:** verify a sender domain in Resend (for example `usesorted.co.za`), then change `MAIL_FROM_ADDRESS` in the server `.env` and redeploy.
- [ ] **Old host:** in Cloudflare, retire `sortd.heyboy.co.za` or redirect it to `usesorted.co.za`.
- [ ] **AWS:** the IAM role `sortd-preview-bedrock` is unused since the move to Gemini (decision 049). Delete or rename it.
- [ ] Delete `~/getsorted/deploy/preview/.env.before-rename` once all is well.

## If something goes wrong

Reverse it: `docker compose down`, rename the database and role back (step 2 with the names swapped), `mv ~/getsorted ~/sortd`, restore `.env.before-rename`, then redeploy the previous `main` commit.
