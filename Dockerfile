# Manolya Pharma — production image (Render / Coolify / Docker)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-reqs

FROM node:22-bookworm AS frontend
WORKDIR /app
COPY package.json package-lock.json vite.config.js tsconfig.json ./
COPY postcss.config.js tailwind.config.js ./
COPY resources ./resources
COPY public ./public
# Ziggy est requis par vue-tsc / app.ts (import vendor/tightenco/ziggy)
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm ci && npm run build

FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip nginx supervisor \
    libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql pcntl intl bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

COPY docker/php.ini /usr/local/etc/php/conf.d/manolya.ini
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
RUN rm -f /usr/local/etc/php-fpm.d/zz-docker.conf \
    && mkdir -p \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      storage/app/public \
      storage/app/temp \
      bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x docker/render-start.sh \
    && php artisan package:discover --ansi || true

EXPOSE 80

# nginx + PHP-FPM + queue worker. Do not use `php artisan serve`:
# a long Excel import freezes the only process → Render 502.
CMD ["/var/www/html/docker/render-start.sh"]
