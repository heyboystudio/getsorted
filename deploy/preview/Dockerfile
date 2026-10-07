# Private test site image (decision 037): PHP 8.4 + Caddy via FrankenPHP.
# Not the production image; the real host is still open (decision 014).
FROM dunglas/frankenphp:1-php8.4-bookworm

RUN install-php-extensions pdo_pgsql pgsql intl gd imagick bcmath zip exif pcntl opcache \
    && apt-get update && apt-get install -y --no-install-recommends libheif1 postgresql-client \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY deploy/preview/php.ini /usr/local/etc/php/conf.d/zz-getsorted.ini
COPY deploy/preview/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN rm -f bootstrap/cache/*.php \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs storage/app/private bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && php artisan package:discover --ansi \
    && php artisan filament:assets \
    && rm -f bootstrap/cache/config.php bootstrap/cache/routes-*.php
