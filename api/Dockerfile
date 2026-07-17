FROM php:8.2-cli

# System libraries + PHP extensions needed by Laravel, Postgres, dompdf, qrcode
RUN apt-get update && apt-get install -y \
    git curl unzip \
    libpq-dev libonig-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_pgsql mbstring bcmath gd zip exif \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# PENTING: buat folder writable SEBELUM composer install.
# post-autoload-dump menjalankan artisan package:discover yang butuh
# bootstrap/cache sudah ada dan bisa ditulis.
RUN mkdir -p bootstrap/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
 && chmod -R 775 storage bootstrap/cache

RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8000

# Render inject $PORT. Cache config, migrate, seed (idempotent), lalu serve.
CMD ["sh", "-c", "php artisan config:cache && php artisan migrate --force && php artisan db:seed --force && php artisan serve --host 0.0.0.0 --port ${PORT:-8000}"]