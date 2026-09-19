#!/usr/bin/env bash
# Deploy backend en el VPS. Ejecutar desde /var/www/gestion-politica-backend
#
# Por defecto NO toca la base de datos (solo código + caché).
# Para aplicar migraciones de forma explícita:
#   RUN_MIGRATE=1 bash scripts/deploy-vps.sh
#
# Nunca ejecuta seeders ni migrate:fresh.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/gestion-politica-backend}"
REMOTE="${DEPLOY_REMOTE:-mio}"
BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-}"
# 0 = no migrar (seguro para producción). 1 = migrate --force
RUN_MIGRATE="${RUN_MIGRATE:-0}"

cd "$APP_DIR"

# Normalizar CRLF del propio script si vino desde Windows
sed -i 's/\r$//' "$0" 2>/dev/null || true

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

if [[ "$RUN_MIGRATE" == "1" ]]; then
  echo "==> [backend] Migraciones (RUN_MIGRATE=1)"
  $PHP_BIN artisan migrate --force
else
  echo "==> [backend] Migraciones OMITIDAS (protege datos del VPS)"
  echo "    Para migrar a mano: RUN_MIGRATE=1 bash scripts/deploy-vps.sh"
  echo "    O solo migrar:      $PHP_BIN artisan migrate --force"
  # Aviso si hay pendientes (no aplica nada)
  if $PHP_BIN artisan migrate:status 2>/dev/null | grep -qi 'Pending'; then
    echo "    AVISO: hay migraciones pendientes. Revísalas antes de aplicarlas."
  fi
fi

echo "==> [backend] Caché y storage"
$PHP_BIN artisan storage:link 2>/dev/null || true
$PHP_BIN artisan config:clear
$PHP_BIN artisan cache:clear
$PHP_BIN artisan route:clear
$PHP_BIN artisan view:clear
$PHP_BIN artisan config:cache || true
$PHP_BIN artisan route:cache || true

echo "==> [backend] OK $(git rev-parse --short HEAD) (RUN_MIGRATE=$RUN_MIGRATE)"
