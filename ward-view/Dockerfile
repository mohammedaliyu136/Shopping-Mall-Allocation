# Container for hosting the Ward View demo (e.g. Render: New -> Web Service,
# Docker runtime, root directory "ward-view"). Uses PHP's built-in server,
# which suits a demo; use PHP-FPM + nginx for production traffic.
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libsqlite3-0 \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-dev --optimize-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p database storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache database

ENV PHP_CLI_SERVER_WORKERS=4 APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr DB_CONNECTION=sqlite
EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
