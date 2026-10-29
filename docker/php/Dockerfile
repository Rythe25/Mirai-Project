# Pinned to an exact patch release (includes the PHP2Shell fix, CVE-2026-109618/109619). Bump by hand for future PHP security releases.
FROM php:8.5.12-fpm-alpine

# Match the WSL user (rythe = 1000) so PHP can write storage/ and bootstrap/cache/.
ARG UID=1000
ARG GID=1000

# OPcache is built into PHP 8.5 and can no longer be installed as an extension.
RUN apk add --no-cache git unzip icu-dev postgresql-dev libzip-dev shadow \
 && docker-php-ext-install pdo_pgsql pgsql intl zip bcmath pcntl \
 && groupmod -o -g ${GID} www-data \
 && usermod -o -u ${UID} -g ${GID} www-data

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# Run php-fpm and `docker compose exec api …` as www-data (uid 1000), so artisan/test/composer
# never leave root-owned files in storage/ that PHP-FPM can't write. Use `exec -u root` for admin work.
USER www-data
