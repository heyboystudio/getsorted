#!/usr/bin/env bash
# Deploys the current checkout to the private test site (decision 037).
# Usage: deploy/preview/deploy.sh   (from the repo root; needs `ssh aws` and Node 24)
set -euo pipefail

HOST="${PREVIEW_HOST:-aws}"
REMOTE_DIR="sortd"
cd "$(git rev-parse --show-toplevel)"

echo "→ Building front-end assets"
PATH="$HOME/.local/bin:$PATH" npm ci --silent
PATH="$HOME/.local/bin:$PATH" npm run build --silent

echo "→ Copying the code (no .env, no local data)"
rsync -az --delete \
  --exclude '.git/' --exclude '.env' --exclude '.env.*' --exclude 'vendor/' --exclude 'node_modules/' \
  --exclude 'storage/app/' --exclude 'storage/logs/' --exclude 'storage/framework/' --exclude 'tests/' \
  --exclude 'deploy/preview/.env' \
  ./ "$HOST:$REMOTE_DIR/"

echo "→ Building and starting containers"
ssh "$HOST" "cd $REMOTE_DIR/deploy/preview && test -f .env && docker compose build -q web && docker compose up -d --remove-orphans"

echo "→ Migrating and caching"
ssh "$HOST" "cd $REMOTE_DIR/deploy/preview && docker compose exec -T web php artisan migrate --force && docker compose exec -T web php artisan db:seed --force && docker compose exec -T web php artisan optimize && docker compose restart queue scheduler >/dev/null"

echo "✓ Deployed"
