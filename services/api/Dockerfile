# ── Composer dependencies ───────────────────────────────────────────────────
FROM composer:2 AS vendor-production
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist \
    --no-progress --optimize-autoloader

FROM composer:2 AS vendor-development
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress

# ── Shared PHP runtime ────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS runtime

# pdo_pgsql needs libpq at runtime and build tools to compile.
# Redis is reached via predis (pure PHP), so no C extension is required.
RUN apk add --no-cache postgresql-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk del .build-deps

WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]

# ── Development image ────────────────────────────────────────────────────────
FROM runtime AS development
RUN apk add --no-cache --virtual .xdebug-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .xdebug-deps
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY --from=vendor-development /app/vendor ./vendor
COPY --from=vendor-development /usr/bin/composer /usr/local/bin/composer
COPY . .

# ── Production image (default target) ────────────────────────────────────────
FROM runtime AS production
COPY docker/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY --from=vendor-production /app/vendor ./vendor
COPY . .
