#!/bin/sh
set -e

# Cache config/routes/views for production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Storage link (idempotent)
php artisan storage:link 2>/dev/null || true

# Start PHP-FPM in background, nginx in foreground
php-fpm -D
exec nginx -g "daemon off;"
