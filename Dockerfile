# Manolya Pharma — production image (Render / Coolify / Docker)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-reqs

FROM node:22-bookworm AS frontend
WORKDIR /app
COPY package.json package-lock.json vite.config.js tsconfig.json ./
COPY resources ./resources
COPY public ./public
# Ziggy est requis par vue-tsc / app.ts (import vendor/tightenco/ziggy)
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm ci && npm run build

FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql pcntl intl bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      storage/app/public \
      storage/app/temp \
      bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && php artisan package:discover --ansi || true

EXPOSE 80

COPY docker/render-start.sh /usr/local/bin/render-start.sh
RUN chmod +x /usr/local/bin/render-start.sh

# Render injecte $PORT ; Coolify/Docker local → 80 par défaut
# Le script lance aussi un mini worker queue (mails PDF clôture)
CMD ["/usr/local/bin/render-start.sh"]
