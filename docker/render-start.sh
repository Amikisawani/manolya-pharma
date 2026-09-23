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
  export DB_URL
fi

# APP_URL : Render expose RENDER_EXTERNAL_URL ; domaine custom en dernier recours
if [ -z "${APP_URL:-}" ] || [ "$APP_URL" = "http://localhost" ] || [ "$APP_URL" = "http://localhost:8000" ]; then
  if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
  else
    export APP_URL="https://manolya-pharma.site"
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
if [ -n "${DB_URL:-}" ]; then
  DB_URL="$(printf '%s' "$DB_URL" | sed 's/-pooler\././g')"
  export DB_URL
fi
if [ -n "${DB_HOST:-}" ]; then
  DB_HOST="$(printf '%s' "$DB_HOST" | sed 's/-pooler\././g')"
  export DB_HOST
fi

echo "DB check: DB_HOST=${DB_HOST:-<empty>} DB_DATABASE=${DB_DATABASE:-<empty>} DB_URL_SET=$([ -n "${DB_URL:-}" ] && echo yes || echo no)"
echo "APP_URL=${APP_URL:-<empty>} PORT=${PORT:-80}"

if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY manquant (base64:...)."
  exit 1
fi

if [ -z "${DB_URL:-}" ] && { [ -z "${DB_HOST:-}" ] || [ "$DB_HOST" = "127.0.0.1" ] || [ "$DB_HOST" = "localhost" ]; }; then
  echo "WARN: Postgres Neon non configuré. nginx démarre quand même pour éviter un 502 Render."
  echo "Ajoute DB_URL=postgresql://user:pass@host/neondb?sslmode=require"
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
  bootstrap/cache \
  /var/log/supervisor \
  /run/nginx \
  /var/log/nginx

# Bind Render $PORT immediately. Migrations run afterwards via supervisor
# so a Neon froid / migrate lent ne laisse plus le proxy sans listener (502).
PORT="${PORT:-80}"
sed "s/__LISTEN_PORT__/${PORT}/g" /var/www/html/docker/nginx-site.conf \
    > /etc/nginx/sites-available/default
ln -sfn /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
rm -f /etc/nginx/sites-enabled/default.bak

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

exec /usr/bin/supervisord -n -c /var/www/html/docker/supervisord.conf
