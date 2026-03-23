FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    supervisor \
    nginx \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    intl \
    opcache \
    && docker-php-ext-enable pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create JWT directory
RUN mkdir -p /var/www/html/config/jwt \
    && chown -R www-data:www-data /var/www/html/config/jwt

# Copy PHP and Nginx configs
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy supervisor config
COPY docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port 9000 (PHP-FPM)
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start supervisor (manages php-fpm and nginx)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
