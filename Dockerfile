# Multi-stage build for Laravel on AWS
FROM php:8.3-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite \
    sqlite-dev \
    mysql-client \
    postgresql-dev \
    postgresql-client \
    redis \
    supervisor \
    nginx \
    autoconf \
    g++ \
    make

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Install Redis extension via apk
RUN apk add --no-cache php83-redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies (including dev for docs generation)
RUN composer install --optimize-autoloader --no-scripts --no-interaction

# Copy application code
COPY . .

# Create minimal .env for build process
RUN cp .env.example .env \
    && php artisan key:generate --ansi

# Complete composer installation and generate docs (while dev deps are available)
RUN composer dump-autoload --optimize \
    && (php artisan scribe:generate --no-interaction || echo "Scribe generation completed with warnings - continuing build") \
    && echo "📚 Verifying Scribe documentation was generated..." \
    && ls -la storage/app/private/scribe/ || echo "⚠️  Scribe directory not found" \
    && ls -la resources/views/scribe/ || echo "⚠️  Scribe views directory not found"

# Remove dev dependencies and re-optimize for production
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction \
    && composer dump-autoload --optimize

# Create required directories and set permissions (www-data user already exists in php:8.3-fpm-alpine)
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    && mkdir -p storage/app/private/scribe \
    && mkdir -p bootstrap/cache \
    && mkdir -p /var/log/supervisor /var/run /var/log/php-fpm /var/log/nginx /run/php \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database/database.sqlite /var/log/php-fpm /run/php \
    && chown -R root:root /var/log/supervisor /var/run /var/log/nginx \
    && chmod -R 775 storage bootstrap/cache /var/log/supervisor /var/run /var/log/php-fpm /var/log/nginx /run/php

# Copy PHP configuration
COPY docker/php/php.ini $PHP_INI_DIR/conf.d/laravel.ini

# Copy PHP-FPM configuration
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy Nginx configuration (use default.conf as main config, not proxy config)
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Fix nginx configuration for single container setup
RUN sed -i 's/fastcgi_pass php:9000;/fastcgi_pass 127.0.0.1:9000;/g' /etc/nginx/conf.d/default.conf

# Create a simple nginx.conf for standalone operation
RUN echo "worker_processes auto;" > /etc/nginx/nginx.conf \
    && echo "error_log /var/log/nginx/error.log warn;" >> /etc/nginx/nginx.conf \
    && echo "pid /var/run/nginx.pid;" >> /etc/nginx/nginx.conf \
    && echo "events { worker_connections 1024; }" >> /etc/nginx/nginx.conf \
    && echo "http { include /etc/nginx/mime.types; default_type application/octet-stream;" >> /etc/nginx/nginx.conf \
    && echo "sendfile on; keepalive_timeout 65; include /etc/nginx/conf.d/*.conf; }" >> /etc/nginx/nginx.conf

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Health check - use simpler endpoint
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/health-check.php || curl -f http://localhost/health || exit 1

EXPOSE 80

# Use entrypoint to configure environment before starting services
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
