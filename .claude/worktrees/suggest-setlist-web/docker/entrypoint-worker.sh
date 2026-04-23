#!/bin/sh
set -e

# Cache config for production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
fi

echo "Starting worker: queue=${WORKER_QUEUE} timeout=${WORKER_TIMEOUT} tries=${WORKER_TRIES}"

exec php artisan queue:work redis \
    --queue="${WORKER_QUEUE}" \
    --sleep="${WORKER_SLEEP}" \
    --tries="${WORKER_TRIES}" \
    --timeout="${WORKER_TIMEOUT}" \
    --max-jobs=500 \
    --max-time=3600
