# Manolya Pharma — production image (Coolify / Docker)
FROM node:22-bookworm AS frontend
WORKDIR /app
COPY package.json package-lock.json vite.config.js tsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm ci && npm run build

FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev libicu-dev \
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

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/public storage/app/temp bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && php artisan package:discover --ansi || true

EXPOSE 80

CMD ["sh", "-c", "php artisan migrate --force && php artisan storage:link || true && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan serve --host=0.0.0.0 --port=80"]
