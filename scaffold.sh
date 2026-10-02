#!/bin/sh
# One-time: generate the Laravel skeleton with Docker only (no PHP/Composer on the host).
# Never overwrites files that already exist in the repo. Override version: LARAVEL_VERSION='^12.0' make scaffold
set -eu

LARAVEL_VERSION="${LARAVEL_VERSION:-^13.0}"
TMP=".scaffold-tmp"

if [ -f artisan ]; then
  echo "artisan already exists: skeleton present, nothing to do."
  exit 0
fi
command -v docker >/dev/null 2>&1 || { echo "Docker is required."; exit 1; }

echo ">> Creating Laravel ${LARAVEL_VERSION} skeleton (first run downloads packages, takes a minute)"

docker run --rm -u "$(id -u):$(id -g)" \
  -e COMPOSER_HOME=/tmp/composer -e LARAVEL_VERSION="$LARAVEL_VERSION" -e TMP="$TMP" \
  -v "$PWD":/app -w /app composer:2 sh -ec '
  rm -rf "$TMP"
  composer create-project "laravel/laravel:$LARAVEL_VERSION" "$TMP" --prefer-dist --no-interaction --no-scripts --no-install
  cd "$TMP"
  composer config platform.php 8.4.0
  composer require laravel/sanctum --no-interaction --no-scripts --no-install --ignore-platform-req="ext-*"
  cd ..
  (cd "$TMP" && find . -path ./vendor -prune -o -type f -print) | while read -r f; do
    if [ ! -e "$f" ]; then
      mkdir -p "$(dirname "$f")"
      mv "$TMP/$f" "$f"
    fi
  done
  rm -rf "$TMP"
  rm -f tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php
  rm -f database/migrations/0001_01_01_*.php
'

echo ">> Done. Next: docker compose up --build"
