#!/bin/sh
set -e

echo "Running database migrations..."
php artisan migrate --force

echo "Starting php-fpm..."
exec php-fpm
