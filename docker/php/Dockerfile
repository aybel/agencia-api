FROM php:8.4-fpm-alpine

# Extensiones de PHP: PostgreSQL, Redis (phpredis), y las habituales de Laravel
RUN apk add --no-cache git unzip bash postgresql-client libpq icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql pcntl bcmath intl zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Mismo UID/GID que tu usuario para que los archivos creados en el volumen no queden como root
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} app && adduser -D -u ${UID} -G app app

WORKDIR /var/www/html
USER app

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
