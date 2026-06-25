FROM php:8.2-fpm

# Cài Nginx, Supervisor (quản lý 2 tiến trình PHP-FPM + Nginx cùng lúc), và các extension PHP
RUN apt-get update && apt-get install -y \
    nginx supervisor git unzip libzip-dev libpng-dev libonig-dev libxml2-dev gettext-base \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Bật OPcache để tăng tốc xử lý PHP
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=0'; \
} > /usr/local/etc/php/conf.d/opcache-custom.ini

# Cài Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Cấu hình Nginx + Supervisor
COPY docker/nginx.conf.template /etc/nginx/sites-available/default.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]
