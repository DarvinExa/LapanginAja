FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git curl unzip \
    libpq-dev libonig-dev libzip-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_pgsql mbstring bcmath gd zip exif \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

# Harus dibuat sebelum composer install karena package:discover
# membutuhkan bootstrap/cache.
RUN mkdir -p \
    bootstrap/cache \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
 && chmod -R 775 bootstrap/cache storage

# Tidak memakai --no-dev karena DatabaseSeeder saat ini menggunakan
# User::factory() dan Tenant::factory(), yang membutuhkan Faker.
RUN composer install --optimize-autoloader --no-interaction

EXPOSE 8000

CMD ["sh", "-c", "php artisan config:cache && php artisan migrate --force && php artisan db:seed --force || exit 1; php artisan queue:work --sleep=3 --tries=3 --timeout=120 > /proc/1/fd/1 2>&1 & php artisan schedule:work > /proc/1/fd/1 2>&1 & exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]