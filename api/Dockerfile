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

RUN composer install --no-dev --optimize-autoloader --no-interaction

# Ensure writable framework directories exist
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# Render injects $PORT. Cache config, run migrations, then serve.
CMD ["sh", "-c", "php artisan config:cache && php artisan migrate --force && php artisan serve --host 0.0.0.0 --port ${PORT:-8000}"]
