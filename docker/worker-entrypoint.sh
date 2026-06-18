#!/usr/bin/env sh
set -e
cd /var/www/html

php artisan queue:work --sleep=3 --tries=3 --max-time=3600
