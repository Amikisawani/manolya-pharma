# Manolya Pharma — production image (Coolify / Render / Docker)
FROM node:22-bookworm AS frontend
WORKDIR /app
COPY package.json package-lock.json vite.config.js tsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm ci && npm run build

FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip nginx supervisor \
    libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql pcntl intl bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
COPY --from=frontend /app/public/build ./public/build

COPY docker/php.ini /usr/local/etc/php/conf.d/manolya.ini
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
RUN rm -f /usr/local/etc/php-fpm.d/zz-docker.conf \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/public storage/app/temp bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x docker/start.sh \
    && php artisan package:discover --ansi || true

EXPOSE 80

# nginx + php-fpm + queue worker. Do not use `php artisan serve` in prod:
# a long Excel import would freeze the only process → Render 502 Bad Gateway.
CMD ["/var/www/html/docker/start.sh"]
