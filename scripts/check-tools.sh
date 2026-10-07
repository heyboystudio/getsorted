#!/usr/bin/env bash
# Local development smoke test; never connects to a configured application DB.
set -euo pipefail

for getsorted_tool in php composer node npm docker; do
    command -v "$getsorted_tool" >/dev/null || { printf 'Missing tool: %s\n' "$getsorted_tool" >&2; exit 1; }
done
php -r 'if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) { fwrite(STDERR, "PHP 8.4 required\n"); exit(1); } foreach (["curl", "mbstring", "dom", "zip", "pdo_pgsql", "intl", "bcmath", "gd"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: $extension\n"); exit(1); } } echo "PHP ".PHP_VERSION." and required extensions: OK\n";'
composer --version
node -e 'if (Number(process.versions.node.split(".")[0]) !== 24) { console.error("Node 24 LTS required"); process.exit(1); } console.log("Node " + process.version + ": OK");'
npm --version
if [[ -n ${DOCKER_CONTEXT:-} ]]; then
    getsorted_endpoint=$(docker context inspect "$DOCKER_CONTEXT" --format '{{.Endpoints.docker.Host}}')
else
    getsorted_endpoint=${DOCKER_HOST:-$(docker context inspect --format '{{.Endpoints.docker.Host}}')}
fi
if [[ $getsorted_endpoint != unix://* ]]; then
    printf '%s\n' 'Tool checks require a local Docker Unix socket; remote endpoints are refused.' >&2
    exit 1
fi
docker version --format 'Docker client {{.Client.Version}}, server {{.Server.Version}}'
docker compose version

getsorted_container=''
cleanup() {
    if [[ -n $getsorted_container ]]; then
        docker rm -f -v "$getsorted_container" >/dev/null
    fi
}
trap cleanup EXIT
# A fresh random password, no host ports, no network, no reused database volume.
export POSTGRES_PASSWORD
POSTGRES_PASSWORD=$(php -r 'echo bin2hex(random_bytes(32));')
getsorted_container=$(docker run --detach --network none --env POSTGRES_PASSWORD postgis/postgis:17-3.5-alpine)
unset POSTGRES_PASSWORD
getsorted_ready=false
for ((getsorted_attempt=0; getsorted_attempt<30; getsorted_attempt++)); do
    if docker exec "$getsorted_container" pg_isready -h 127.0.0.1 -U postgres >/dev/null 2>&1; then
        getsorted_ready=true
        break
    fi
    sleep 1
done
if [[ $getsorted_ready != true ]]; then
    printf '%s\n' 'Disposable PostgreSQL container did not become ready within 30 seconds.' >&2
    exit 1
fi
docker exec -i "$getsorted_container" psql -X -U postgres -v ON_ERROR_STOP=1 <<'SQL'
SELECT version();
SELECT PostGIS_Full_Version();
DO $$
BEGIN
    ASSERT current_setting('server_version_num')::int BETWEEN 170000 AND 179999,
        'PostgreSQL 17 required';
    ASSERT ST_DWithin(
        ST_SetSRID(ST_MakePoint(31.02, -29.86), 4326)::geography,
        ST_SetSRID(ST_MakePoint(31.021, -29.861), 4326)::geography,
        200
    ), 'PostGIS geography distance query failed';
END $$;
SQL
printf '%s\n' 'All Phase 0 step 1 tool checks passed.'
