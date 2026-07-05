#!/bin/bash
set -e

export PORT="${PORT:-10000}"
envsubst '${PORT}' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default

# Xóa cache cũ trước khi cache lại
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Cache lại
php artisan config:cache
php artisan route:cache
php artisan migrate --force

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
