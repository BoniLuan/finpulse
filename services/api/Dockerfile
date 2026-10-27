# Composer runs on the same PHP and extensions as the application.
FROM composer:2.10.3@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc AS composer-binary
FROM php:8.5.12-fpm-alpine@sha256:f09d9172dd28b8696aef729ca2c2081a132c1f8d581218c99497d552c82a92df AS runtime
RUN apk add --no-cache postgresql-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk del .build-deps
WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]

FROM runtime AS vendor-production
COPY --from=composer-binary /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist \
    --no-progress --optimize-autoloader

FROM vendor-production AS vendor-development
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress

FROM runtime AS development
RUN apk add --no-cache --virtual .xdebug-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug-3.5.3 \
    && docker-php-ext-enable xdebug \
    && apk del .xdebug-deps
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY --from=vendor-development /var/www/html/vendor ./vendor
COPY --from=composer-binary /usr/bin/composer /usr/local/bin/composer
COPY . .

FROM runtime AS production
COPY docker/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY --from=vendor-production /var/www/html/vendor ./vendor
COPY . .
