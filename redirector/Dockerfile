FROM php:8.2-fpm

# Install necessary dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libzip-dev zip unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Create a writable session directory
RUN mkdir -p /var/lib/php/sessions && chmod -R 777 /var/lib/php/sessions
ENV PHP_SESSION_SAVE_PATH="/var/lib/php/sessions"

# Set working directory
WORKDIR /var/www/redirector

# Copy source code into container
COPY . .

# Expose the PHP-FPM port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
