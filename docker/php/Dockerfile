FROM php:8.3-fpm-bookworm

# mariadb-client: Laravel lo usa para cargar database/schema/mariadb-schema.sql
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libpng-dev mariadb-client \
    && docker-php-ext-install pdo_mysql zip intl gd bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-suilaft.ini

WORKDIR /var/www/backend
