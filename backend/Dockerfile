FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev zip unzip libonig-dev curl git \
    && docker-php-ext-install pdo pdo_mysql zip gd

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN mkdir -p /var/lib/php/sessions && chmod -R 777 /var/lib/php/sessions
ENV PHP_SESSION_SAVE_PATH="/var/lib/php/sessions"

WORKDIR /var/www/backend

COPY composer.json composer.lock ./

RUN composer install

COPY . .

EXPOSE 9000

# Default command
CMD ["php-fpm"]
