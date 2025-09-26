#!/bin/bash
set -e

echo "🚀 Starting SHRT application deployment (Fixed entrypoint)..."
echo "📍 Entrypoint script location: $(readlink -f "$0")"
echo "📍 Working directory: $(pwd)"
echo "📍 User: $(whoami)"

# Set secure defaults for production
export APP_ENV=${APP_ENV:-production}
export APP_DEBUG=${APP_DEBUG:-false}
export LOG_CHANNEL=${LOG_CHANNEL:-stderr}
export LOG_LEVEL=${LOG_LEVEL:-error}

echo "📋 Environment: $APP_ENV"
echo "🔍 Debug mode: $APP_DEBUG"

# Database Configuration with Fallback
if [ -z "$DB_HOST" ] || [ -z "$DB_PASSWORD" ]; then
    echo "⚠️  Database not configured via secrets, using SQLite fallback..."
    export DB_CONNECTION=sqlite
    export DB_DATABASE=/var/www/html/database/database.sqlite

    # Ensure database directory exists
    mkdir -p /var/www/html/database
    if [ ! -f "$DB_DATABASE" ]; then
        touch "$DB_DATABASE"
        chmod 664 "$DB_DATABASE"
        chown www-data:www-data "$DB_DATABASE"
    fi
else
    echo "✅ Database configured: $DB_HOST"
    export DB_CONNECTION=mysql
fi

# Cache Configuration with Redis Fallback
if [ -z "$REDIS_HOST" ]; then
    echo "⚠️  Redis not configured, using database cache..."
    export CACHE_STORE=database
    export SESSION_DRIVER=database
else
    echo "🧪 Testing Redis connection..."
    if timeout 3 redis-cli -h "$REDIS_HOST" -p "${REDIS_PORT:-6379}" ping > /dev/null 2>&1; then
        echo "✅ Redis connected: $REDIS_HOST"
        export CACHE_STORE=redis
        export SESSION_DRIVER=redis
    else
        echo "❌ Redis connection failed, falling back to database cache..."
        export CACHE_STORE=database
        export SESSION_DRIVER=database
    fi
fi

# Create required directories with proper ownership
echo "📁 Setting up storage directories..."
mkdir -p storage/framework/{sessions,views,cache,compiled}
mkdir -p storage/{app,logs,app/public}
mkdir -p bootstrap/cache
mkdir -p /var/log/php-fpm
mkdir -p /run/php

# Set correct permissions for Laravel
echo "🔐 Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/log/php-fpm
chown -R www-data:www-data /run/php

# Laravel requires 775 for storage and bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache
chmod -R 755 /var/log/php-fpm
chmod -R 755 /run/php

# Clear all caches to avoid permission issues
echo "🧹 Clearing caches..."
# Laravel 12 optimize:clear clears all cached bootstrap files
php artisan optimize:clear
php artisan cache:clear

# Test database connection before proceeding
echo "🔌 Testing database connection..."
if ! php artisan migrate:status > /dev/null 2>&1; then
    echo "❌ Database connection failed, but continuing with SQLite fallback"
    export DB_CONNECTION=sqlite
    export DB_DATABASE=/var/www/html/database/database.sqlite
    if [ ! -f "$DB_DATABASE" ]; then
        touch "$DB_DATABASE"
        chmod 664 "$DB_DATABASE"
        chown www-data:www-data "$DB_DATABASE"
    fi
fi

# Run migrations with error handling
echo "📊 Running database migrations..."
if php artisan migrate --force --no-interaction; then
    echo "✅ Migrations completed successfully"
else
    echo "⚠️  Migration failed, attempting to continue..."
fi

# Create storage link for public file access
echo "🔗 Creating storage link..."
if php artisan storage:link --force; then
    echo "✅ Storage link created successfully"
else
    echo "⚠️  Storage link failed, but continuing..."
fi

# Cache for production only after successful setup
if [ "$APP_ENV" = "production" ]; then
    echo "⚡ Optimizing for production..."
    # Laravel 12 optimize command - caches config, routes, views, and bootstrap
    php artisan optimize
    echo "✅ Production optimizations complete"
fi

# Final health check
echo "🏥 Running health check..."
if php artisan tinker --execute="echo 'Laravel is ready';" > /dev/null 2>&1; then
    echo "✅ Laravel is healthy and ready"
else
    echo "⚠️  Laravel health check failed, but proceeding..."
fi

echo "🎯 Starting services..."
# Start supervisor to manage nginx and php-fpm
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf