#!/bin/bash
set -e

# Render cấp PORT qua biến môi trường - thay vào nginx config
export PORT="${PORT:-10000}"
envsubst '${PORT}' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default

# Cache config + route, chạy migration (idempotent - chỉ chạy migration chưa chạy)
php artisan config:cache
php artisan route:cache
php artisan migrate --force

# Chạy PHP-FPM + Nginx cùng lúc qua Supervisor (cho phép xử lý nhiều request đồng thời thật)
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
