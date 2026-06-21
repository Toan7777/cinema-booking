FROM php:8.2-cli

# Cài các extension PHP cần thiết cho Laravel + MySQL
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

# Cài Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

# Render cấp PORT qua biến môi trường, Laravel phải lắng nghe đúng cổng đó
EXPOSE 10000

CMD php artisan migrate --force && php artisan db:seed --class=CinemaSeeder --force && php artisan serve --host 0.0.0.0 --port ${PORT:-10000}
