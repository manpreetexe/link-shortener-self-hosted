FROM php:8.2-fpm

# Install GD
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Setup sessions directory
RUN mkdir -p /var/lib/php/sessions && chmod -R 777 /var/lib/php/sessions
ENV PHP_SESSION_SAVE_PATH="/var/lib/php/sessions"

WORKDIR /var/www/html

COPY . .

EXPOSE 9000

CMD ["php-fpm"]
