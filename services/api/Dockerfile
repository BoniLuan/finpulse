# ── Stage 1: composer dependencies ────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress || \
    composer update --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

# ── Stage 2: runtime (php-fpm) ────────────────────────────────────────────────
FROM php:8.3-fpm-alpine

RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .

# php-fpm listens on 9000; the gateway connects via FastCGI.
EXPOSE 9000
CMD ["php-fpm"]
