#!/bin/sh
set -e

php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Sur le plan gratuit (1 seul service web) : petit worker en arrière-plan
# pour envoyer les PDF de clôture sans service "Background Worker" payant.
(
  while true; do
    php artisan queue:work database --stop-when-empty --tries=3 --max-time=55 || true
    sleep 5
  done
) &

PORT="${PORT:-80}"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
