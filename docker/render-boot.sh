#!/bin/sh
# Runs after nginx/php-fpm are already listening. Failures must not kill the web process.

set +e

echo "boot: migrate / bootstrap (retries, non-blocking for /up et /login)"

ok=0
i=1
while [ "$i" -le 6 ]; do
  if php artisan migrate --force; then
    ok=1
    break
  fi
  echo "boot: migrate failed (attempt ${i}/6) — retry"
  sleep $((i * 4))
  i=$((i + 1))
done

if [ "$ok" -ne 1 ]; then
  echo "WARN: migrate still failing — /login may 500 until Neon/DB is reachable"
fi

php artisan manolya:bootstrap || true
php artisan storage:link || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Factures 22/09/2026 — lots ACH-* déjà remplis ignorés ; prix = PU facture × 1,2
if [ "${IMPORT_INVOICE_PURCHASES:-true}" != "false" ]; then
  echo "boot: import factures d’achat (catalogue + stock)"
  php artisan manolya:import-invoice-purchases || echo "WARN: import factures failed"
fi

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

echo "boot: done"
exit 0
