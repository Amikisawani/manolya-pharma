#!/bin/sh
set -e

# Neon fournit souvent DATABASE_URL ; Laravel attend DB_URL
if [ -n "${DATABASE_URL:-}" ] && [ -z "${DB_URL:-}" ]; then
  export DB_URL="$DATABASE_URL"
fi

# Nettoyage DB_URL (quotes / channel_binding)
if [ -n "${DB_URL:-}" ]; then
  DB_URL="$(printf '%s' "$DB_URL" | tr -d '"' | tr -d "'")"
  DB_URL="$(printf '%s' "$DB_URL" | sed 's/[?&]channel_binding=require//g')"
  # Laravel/PDO : postgres:// ou postgresql:// OK ; forcer postgresql si besoin
  export DB_URL
fi

# APP_URL : Render expose RENDER_EXTERNAL_URL (https://xxx.onrender.com)
if [ -z "${APP_URL:-}" ] || [ "$APP_URL" = "http://localhost" ] || [ "$APP_URL" = "http://localhost:8000" ]; then
  if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
  fi
fi

# Nettoyage APP_URL (guillemets, espaces, slash final, schéma manquant)
if [ -n "${APP_URL:-}" ]; then
  APP_URL="$(printf '%s' "$APP_URL" | tr -d '"' | tr -d "'" | tr -d ' ')"
  APP_URL="$(printf '%s' "$APP_URL" | sed 's:/*$::')"
  case "$APP_URL" in
    http://*|https://*) ;;
    *) APP_URL="https://$APP_URL" ;;
  esac
  export APP_URL
fi

# Neon pooler (-pooler) casse les migrations Laravel (DDL en transaction).
# Forcer l'endpoint Direct.
if [ -n "${DB_URL:-}" ]; then
  DB_URL="$(printf '%s' "$DB_URL" | sed 's/-pooler\././g')"
  export DB_URL
fi
if [ -n "${DB_HOST:-}" ]; then
  DB_HOST="$(printf '%s' "$DB_HOST" | sed 's/-pooler\././g')"
  export DB_HOST
fi

echo "DB check: DB_HOST=${DB_HOST:-<empty>} DB_DATABASE=${DB_DATABASE:-<empty>} DB_URL_SET=$([ -n "${DB_URL:-}" ] && echo yes || echo no)"
echo "APP_URL=${APP_URL:-<empty>}"

if [ -z "${APP_URL:-}" ]; then
  echo "ERROR: APP_URL manquant. Sur Render mets :"
  echo "  APP_URL=https://manolya-web-orgf.onrender.com"
  echo "(sans guillemets, avec https://)"
  exit 1
fi

if [ -z "${DB_URL:-}" ] && { [ -z "${DB_HOST:-}" ] || [ "$DB_HOST" = "127.0.0.1" ] || [ "$DB_HOST" = "localhost" ]; }; then
  echo "ERROR: Postgres Neon non configuré sur Render."
  echo "Ajoute DB_URL=postgresql://user:pass@host/neondb?sslmode=require"
  exit 1
fi

if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY manquant (base64:...)."
  exit 1
fi

export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_SSLMODE="${DB_SSLMODE:-require}"

mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  storage/app/public \
  storage/app/temp \
  bootstrap/cache

php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
# Non bloquant : Inertia sert surtout du JS ; les Blade PDF restent compilables à la volée
php artisan view:cache || true

(
  while true; do
    php artisan queue:work database --stop-when-empty --tries=3 --max-time=55 || true
    sleep 5
  done
) &

PORT="${PORT:-80}"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
