#!/bin/sh
set -e
cd /var/www/html

PORT="${PORT:-80}"
sed "s/__LISTEN_PORT__/${PORT}/g" /var/www/html/docker/nginx-site.conf \
    > /etc/nginx/sites-available/default
ln -sfn /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

i=0
until php artisan migrate --force; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then
        echo "migrate failed after 30 attempts" >&2
        exit 1
    fi
    echo "waiting for database..."
    sleep 2
done
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
mkdir -p /var/log/supervisor /run/nginx

exec /usr/bin/supervisord -n -c /var/www/html/docker/supervisord.conf
