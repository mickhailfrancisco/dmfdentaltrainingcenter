#!/usr/bin/env sh
set -e
cd /var/www/html

php artisan storage:link --force 2>/dev/null || true
php artisan migrate --force --no-interaction

if [ "${RUN_OPTIMIZE_ON_BOOT:-true}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan filament:optimize
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
