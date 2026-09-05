# Container image for running the application on a hosting platform.
#
# Intended for demonstration and testing. It serves through PHP's built-in
# server, which is fine for a handful of concurrent users; a production
# deployment should sit behind PHP-FPM and nginx.
FROM php:8.3-cli

# The application needs dom, mbstring, iconv, pdo_sqlite and sqlite3, all of
# which the official PHP image bundles. Only Composer's unzip helper and the
# sqlite CLI are added here.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libsqlite3-0 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies first so the layer is cached across code changes.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-dev --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p database storage/logs storage/framework/{cache,sessions,views} bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache database

# PHP's built-in server handles one request per worker; a few workers keep the
# UI responsive while a PDF renders.
ENV PHP_CLI_SERVER_WORKERS=4 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]
