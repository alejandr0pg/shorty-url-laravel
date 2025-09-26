#!/bin/bash

# Pre-deployment health check script
echo "🔍 Running pre-deployment checks..."

# Check required environment variables
REQUIRED_VARS=(
    "APP_KEY"
    "APP_ENV"
    "DB_CONNECTION"
    "DB_HOST"
    "DB_PORT"
    "DB_DATABASE"
    "DB_USERNAME"
    "DB_PASSWORD"
)

echo "Checking required environment variables..."
for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ]; then
        echo "❌ Missing required environment variable: $var"
        exit 1
    else
        echo "✅ $var is set"
    fi
done

# Test database connection
echo "Testing database connection..."
php -r "
try {
    \$pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
    echo '✅ Database connection successful\n';
} catch (Exception \$e) {
    echo '❌ Database connection failed: ' . \$e->getMessage() . '\n';
    exit(1);
}
"

# Check if Redis is configured and test connection if needed
if [ "$CACHE_STORE" = "redis" ]; then
    echo "Testing Redis connection..."
    php -r "
    try {
        \$redis = new Redis();
        \$redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
        echo '✅ Redis connection successful\n';
    } catch (Exception \$e) {
        echo '⚠️  Redis connection failed, will use database cache as fallback\n';
    }
    "
fi

# Clear and optimize for production
echo "Optimizing for production..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache configuration for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

echo "✅ Pre-deployment checks complete!"