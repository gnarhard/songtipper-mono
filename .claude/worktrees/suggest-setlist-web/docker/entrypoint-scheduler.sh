#!/bin/sh
set -e

# Cache config for production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
fi

echo "Starting scheduler..."
exec php artisan schedule:work
