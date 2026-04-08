FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libgd-dev \
    zip \
    unzip \
    $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    intl \
    opcache \
    gd \
    && docker-php-ext-enable pdo_mysql

# Install and enable Xdebug for local debugging
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Xdebug runtime configuration
COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Install PHP dependencies with Composer
# Ensure composer scripts run in production environment and allow composer to run as root in the container
ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install PHP dependencies with Composer (no dev packages)
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Create JWT directory
RUN mkdir -p /var/www/html/config/jwt \
    && chown -R www-data:www-data /var/www/html/config/jwt

# Expose port 9000 (PHP-FPM)
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
