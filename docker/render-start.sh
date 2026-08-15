#!/bin/sh
set -e

# Neon fournit souvent DATABASE_URL ; Laravel attend DB_URL
if [ -n "${DATABASE_URL:-}" ] && [ -z "${DB_URL:-}" ]; then
  export DB_URL="$DATABASE_URL"
fi

# channel_binding=require casse souvent PDO/PHP
if [ -n "${DB_URL:-}" ]; then
  export DB_URL="$(printf '%s' "$DB_URL" | sed 's/[?&]channel_binding=require//g')"
fi

echo "DB check: DB_HOST=${DB_HOST:-<empty>} DB_DATABASE=${DB_DATABASE:-<empty>} DB_URL_SET=$([ -n "${DB_URL:-}" ] && echo yes || echo no)"

if [ -z "${DB_URL:-}" ] && { [ -z "${DB_HOST:-}" ] || [ "$DB_HOST" = "127.0.0.1" ] || [ "$DB_HOST" = "localhost" ]; }; then
  echo "ERROR: Postgres Neon non configuré sur Render."
  echo "Dans Render → manolya-web → Environment, ajoute au minimum :"
  echo "  DB_HOST=ep-....aws.neon.tech"
  echo "  DB_PORT=5432"
  echo "  DB_DATABASE=neondb"
  echo "  DB_USERNAME=neondb_owner"
  echo "  DB_PASSWORD=..."
  echo "  DB_SSLMODE=require"
  echo "OU une seule variable :"
  echo "  DB_URL=postgresql://user:pass@host/neondb?sslmode=require"
  echo "Puis Save + Manual Deploy."
  exit 1
fi

export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_SSLMODE="${DB_SSLMODE:-require}"

php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Sur le plan gratuit (1 seul service web) : petit worker en arrière-plan
(
  while true; do
    php artisan queue:work database --stop-when-empty --tries=3 --max-time=55 || true
    sleep 5
  done
) &

PORT="${PORT:-80}"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
