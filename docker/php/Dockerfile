FROM php:8.5-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    zlib-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    icu-libs \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev

# pdo, mbstring, opcache are bundled/compiled in PHP 8.5+
# pcntl is required by laravel/horizon for worker process management
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        bcmath \
        intl \
        pcntl \
        gd

# Redis extension
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-interaction \
    && php artisan package:discover --ansi

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

COPY docker/php/start.sh /usr/local/bin/start.sh
CMD ["/usr/local/bin/start.sh"]
