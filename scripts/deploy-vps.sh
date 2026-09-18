#!/usr/bin/env bash
# Deploy backend en el VPS. Ejecutar desde /var/www/gestion-politica-backend
set -euo pipefail

# Normalizar CRLF si el repo se editó en Windows
sed -i 's/\r$//' "set -euo pipefail
" 2>/dev/null || true

APP_DIR="${APP_DIR:-/var/www/gestion-politica-backend}"
REMOTE="${DEPLOY_REMOTE:-mio}"
BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-}"

cd "$APP_DIR"

if [[ -z "$PHP_BIN" ]]; then
  if command -v php8.3 >/dev/null 2>&1; then
    PHP_BIN=php8.3
  else
    PHP_BIN=php
  fi
fi

echo "==> [backend] Fetch $REMOTE/$BRANCH"
git remote get-url "$REMOTE" >/dev/null 2>&1 || \
  git remote add "$REMOTE" https://github.com/jchoquecota-sys/gestion-politica-backend.git

git fetch "$REMOTE" "$BRANCH"
git reset --hard "$REMOTE/$BRANCH"

echo "==> [backend] Composer"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> [backend] Migraciones y caché"
$PHP_BIN artisan migrate --force
$PHP_BIN artisan storage:link 2>/dev/null || true
$PHP_BIN artisan config:clear
$PHP_BIN artisan cache:clear
$PHP_BIN artisan route:clear
$PHP_BIN artisan view:clear
$PHP_BIN artisan config:cache || true
$PHP_BIN artisan route:cache || true

echo "==> [backend] OK $(git rev-parse --short HEAD)"
