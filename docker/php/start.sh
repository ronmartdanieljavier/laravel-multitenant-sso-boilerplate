#!/bin/sh
set -e

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs
chmod -R 775 storage bootstrap/cache

echo "Clearing stale caches..."
php artisan optimize:clear

echo "Running database migrations..."
php artisan migrate --force

echo "Caching config..."
php artisan config:cache

echo "Starting php-fpm..."
exec php-fpm
