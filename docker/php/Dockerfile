FROM php:8.2-fpm-alpine

# Match the WSL user (rythe = 1000) so PHP can write storage/ and bootstrap/cache/.
ARG UID=1000
ARG GID=1000

RUN apk add --no-cache git unzip icu-dev postgresql-dev libzip-dev shadow \
 && docker-php-ext-install pdo_pgsql pgsql intl zip bcmath opcache \
 && groupmod -o -g ${GID} www-data \
 && usermod -o -u ${UID} -g ${GID} www-data

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
