# syntax=docker/dockerfile:1.7
# EdgeRX Laravel backend — production image with nginx + php-fpm + supervisord.
#
# BE-11 fix (was: php artisan serve in production): the prod target now runs
# the same setup any real Laravel app uses — nginx fronting, php-fpm pool
# behind it, supervisord as PID 1 keeping both alive. Multi-process, real
# concurrency, restart-on-crash semantics. The `dev` target keeps `artisan
# serve` for local convenience.

############# base #############
# Switched from `cli-alpine` to `fpm-alpine` so php-fpm + tooling ships in one image.
FROM php:8.4-fpm-alpine AS base
WORKDIR /var/www/html

RUN apk add --no-cache \
        bash git curl icu-dev libzip-dev libpng-dev oniguruma-dev \
        postgresql-dev autoconf g++ make linux-headers \
        nginx supervisor \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql intl bcmath zip pcntl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make linux-headers

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

############# dev #############
FROM base AS dev
ENV PHP_MEMORY_LIMIT=512M
RUN echo "memory_limit=${PHP_MEMORY_LIMIT}" > /usr/local/etc/php/conf.d/zz-edgerx.ini
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

############# prod #############
FROM base AS prod
ENV APP_ENV=production
ENV PHP_MEMORY_LIMIT=512M
RUN echo "memory_limit=${PHP_MEMORY_LIMIT}" > /usr/local/etc/php/conf.d/zz-edgerx.ini

# nginx + php-fpm + supervisord configs
COPY docker/nginx.conf            /etc/nginx/nginx.conf
COPY docker/php-fpm-pool.conf     /usr/local/etc/php-fpm.d/zz-edgerx.conf
COPY docker/supervisord.conf      /etc/supervisor/conf.d/supervisord.conf

# App code
COPY . /var/www/html/

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
                 /var/log/nginx /var/log/supervisor /run/nginx \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache /var/log/nginx /run/nginx

EXPOSE 8080

COPY docker-entrypoint.sh /usr/local/bin/edgerx-entrypoint.sh
COPY docker-scheduler-entrypoint.sh /usr/local/bin/edgerx-scheduler-entrypoint.sh
COPY docker-prod-start.sh /usr/local/bin/edgerx-prod-start.sh
RUN chmod +x /usr/local/bin/edgerx-entrypoint.sh \
             /usr/local/bin/edgerx-scheduler-entrypoint.sh \
             /usr/local/bin/edgerx-prod-start.sh

# Run boot tasks (migrate / cache / probe) then hand off to supervisord
# which manages nginx + php-fpm. DO App Platform passes $PORT; nginx listens
# on 8080 by default (matches the App Platform health check).
CMD ["/usr/local/bin/edgerx-prod-start.sh"]
