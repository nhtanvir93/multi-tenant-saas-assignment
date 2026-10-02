#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html

log() { echo "[entrypoint] $*"; }

# queue / scheduler containers start only after `app` is healthy, so they skip bootstrap
if [ "${APP_ROLE:-app}" != "app" ]; then
  exec "$@"
fi

rm -f /tmp/app-ready

if [ ! -f artisan ] || [ ! -f composer.lock ]; then
  log "Laravel skeleton not found. Run 'make scaffold' once, then start again."
  exit 1
fi

if [ ! -f .env ]; then
  cp .env.example .env
  log "Created .env from .env.example"
fi

wait_for() {
  local host="$1" port="$2"
  for _ in $(seq 1 60); do
    if (echo > "/dev/tcp/${host}/${port}") >/dev/null 2>&1; then return 0; fi
    sleep 1
  done
  log "Timed out waiting for ${host}:${port}"
  exit 1
}
wait_for "${DB_HOST:-postgres}" "${DB_PORT:-5432}"
wait_for "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}"

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# Install dependencies when vendor is empty OR composer.lock changed since the last install
LOCK_HASH="$(sha256sum composer.lock | cut -d' ' -f1)"
if [ ! -f vendor/autoload.php ] || [ "$(cat vendor/.lock-hash 2>/dev/null || true)" != "${LOCK_HASH}" ]; then
  log "Installing PHP dependencies (composer.lock changed or vendor empty)..."
  composer install --no-interaction --prefer-dist --no-progress
  echo "${LOCK_HASH}" > vendor/.lock-hash
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

php artisan migrate --force
php artisan db:seed --force   # idempotent reference data only

touch /tmp/app-ready
log "Ready"
exec "$@"
