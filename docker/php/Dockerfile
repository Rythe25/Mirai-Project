FROM php:8.2-fpm-alpine

RUN apk add --no-cache git unzip icu-dev postgresql-dev libzip-dev \
 && docker-php-ext-install pdo_pgsql pgsql intl zip bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
