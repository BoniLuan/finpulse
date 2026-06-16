# ── Stage 1: composer dependencies ────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress || \
    composer update --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

# ── Stage 2: runtime (php-fpm) ────────────────────────────────────────────────
FROM php:8.3-fpm-alpine

# pdo_pgsql needs libpq at runtime and build tools to compile.
# Redis is reached via predis (pure PHP), so no C extension is required.
RUN apk add --no-cache postgresql-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk del .build-deps

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .

# php-fpm listens on 9000; the gateway connects via FastCGI.
EXPOSE 9000
CMD ["php-fpm"]
