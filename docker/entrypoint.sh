#!/bin/sh
set -e

# Render injects $PORT; default matches the Dockerfile's EXPOSE.
PORT="${PORT:-10000}"

# Laravel needs an APP_KEY. Prefer the one set in the dashboard; only
# generate an ephemeral one as a last resort so the app still boots.
if [ -z "${APP_KEY}" ]; then
    echo "WARNING: APP_KEY is not set. Generating an ephemeral key."
    echo "         Sessions and encrypted data will be invalidated on each"
    echo "         deploy until you set APP_KEY in the Render dashboard."
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
fi

# Cache config/routes/views for production speed. Done at boot rather than
# build time because the values depend on runtime environment variables.
php artisan config:cache
php artisan view:cache

# NOTE: `route:cache` is deliberately skipped. routes/web.php defines
# /dashboard as a closure, and Laravel cannot serialize closure routes
# ("Unable to prepare route [dashboard] for serialization"). Converting
# that closure into a controller action would let us cache routes for a
# small extra speed win.

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    echo "Seeding demo data..."
    php artisan db:seed --force
fi

php artisan storage:link 2>/dev/null || true

echo "Starting server on 0.0.0.0:${PORT}"
exec php -S "0.0.0.0:${PORT}" -t public public/index.php
