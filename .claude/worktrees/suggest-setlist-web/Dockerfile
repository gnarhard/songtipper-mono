# =============================================================================
# Song Tipper Web - Multi-stage Dockerfile
# Targets: web (PHP-FPM + nginx), worker, scheduler
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Composer dependencies
# -----------------------------------------------------------------------------
FROM composer:2 AS composer

WORKDIR /app
COPY composer.json composer.lock ./
# --ignore-platform-reqs: extensions (bcmath, imagick, etc.) are in the runtime image, not this build stage
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

# -----------------------------------------------------------------------------
# Stage 2: Frontend assets (Vite build)
# -----------------------------------------------------------------------------
FROM node:22-alpine AS node

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

COPY resources/ resources/
COPY theme/ theme/
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY public/ public/
RUN npm run build

# -----------------------------------------------------------------------------
# Stage 3: PHP base image with all extensions + Ghostscript
# NOTE: Using PHP 8.4 because the imagick extension does not yet compile
# against PHP 8.5. Bump to 8.5-fpm-alpine once imagick adds support.
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

# System dependencies: Ghostscript (PDF rendering), ImageMagick, and libs
RUN apk add --no-cache \
    ghostscript \
    imagemagick \
    imagemagick-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        xml \
        bcmath \
        zip \
        gd \
        intl \
        pcntl \
        opcache \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    # Clean up build deps
    && apk del $PHPIZE_DEPS imagemagick-dev \
    && rm -rf /tmp/pear

# Override ImageMagick policy to allow PDF operations (required for chart rendering)
RUN if [ -f /etc/ImageMagick-7/policy.xml ]; then \
        sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' /etc/ImageMagick-7/policy.xml; \
    elif [ -f /etc/ImageMagick-6/policy.xml ]; then \
        sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' /etc/ImageMagick-6/policy.xml; \
    fi

# PHP configuration for production
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini
COPY docker/php/uploads.ini $PHP_INI_DIR/conf.d/uploads.ini

WORKDIR /var/www/html

# Copy application code with dependencies
COPY --from=composer /app/vendor vendor/
COPY . .
COPY --from=node /app/public/build public/build/

# Clear stale bootstrap cache (dev packages like laravel/boost get cached locally)
# and re-discover packages for production (no-dev) dependencies only
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# -----------------------------------------------------------------------------
# Stage 4: Web (PHP-FPM + nginx in a single container)
# -----------------------------------------------------------------------------
FROM base AS web

RUN apk add --no-cache nginx curl

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/entrypoint-web.sh /usr/local/bin/entrypoint-web.sh
RUN chmod +x /usr/local/bin/entrypoint-web.sh

EXPOSE 80

ENTRYPOINT ["entrypoint-web.sh"]

# -----------------------------------------------------------------------------
# Stage 5: Queue worker
# -----------------------------------------------------------------------------
FROM base AS worker

COPY docker/entrypoint-worker.sh /usr/local/bin/entrypoint-worker.sh
RUN chmod +x /usr/local/bin/entrypoint-worker.sh

# WORKER_QUEUE env var selects which queue(s) to process
ENV WORKER_QUEUE=default
ENV WORKER_TIMEOUT=120
ENV WORKER_TRIES=3
ENV WORKER_SLEEP=3

ENTRYPOINT ["entrypoint-worker.sh"]

# -----------------------------------------------------------------------------
# Stage 6: Scheduler
# -----------------------------------------------------------------------------
FROM base AS scheduler

COPY docker/entrypoint-scheduler.sh /usr/local/bin/entrypoint-scheduler.sh
RUN chmod +x /usr/local/bin/entrypoint-scheduler.sh

ENTRYPOINT ["entrypoint-scheduler.sh"]
