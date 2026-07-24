# AgroAide Laravel API — production image for Coolify
# PHP 8.3 (composer.json requires ^8.2), nginx + php-fpm + schedule:work
# No queue worker: codebase has zero ShouldQueue jobs/listeners.

# -----------------------------------------------------------------------------
# Stage 1: Composer dependencies
# -----------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

# -----------------------------------------------------------------------------
# Stage 2: Vite frontend assets (welcome blade + default Laravel UI)
# -----------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json ./
# No package-lock.json in repo — install from package.json
RUN npm install

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# -----------------------------------------------------------------------------
# Stage 3: Runtime (nginx + php-fpm + supervisor + Laravel scheduler)
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm

LABEL org.opencontainers.image.title="AgroAide Backend" \
      org.opencontainers.image.description="Laravel 12 API for AgroAide (Coolify)"

ENV APP_HOME=/var/www/html \
    PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

# System deps + nginx + supervisor
# PHP extensions actually needed by this app:
#   pdo_mysql  — Coolify/production DB (DB_CONNECTION=mysql)
#   mbstring, xml, ctype, fileinfo, tokenizer — Laravel core
#   openssl    — HTTPS clients + FCM JWT signing
#   zip        — Composer packages / archives
#   bcmath     — Laravel / money-safe math
#   intl       — localization
#   opcache    — production performance
#   pcntl      — schedule:work signal handling
# NOT installed (not used by current config/code):
#   gd/imagick, redis, pdo_pgsql, memcached, exif
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        unzip \
        git \
        libzip-dev \
        libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        zip \
        bcmath \
        intl \
        pcntl \
        opcache \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

# PHP production tweaks
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# nginx / supervisor / entrypoint
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/log/supervisor /run/nginx \
    && chown -R www-data:www-data /var/log/nginx /run/nginx

WORKDIR ${APP_HOME}

# Application code
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

# Ensure writable dirs exist and are owned by php-fpm user
RUN mkdir -p \
        storage/app/public \
        storage/app/private \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && rm -f public/hot

# Drop root for app files where possible; supervisord still starts as root
# so nginx can bind :80 (Coolify expects port 80). php-fpm workers run as www-data.
USER root

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
