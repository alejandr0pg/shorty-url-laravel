#!/bin/bash

echo "🔍 Laravel Configuration Verification Script"
echo "=============================================="

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to print status
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ $2${NC}"
        return 0
    else
        echo -e "${RED}❌ $2${NC}"
        return 1
    fi
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

echo ""
echo "📋 Checking Laravel Requirements..."

# Check PHP version
if command_exists php; then
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    print_status 0 "PHP installed: $PHP_VERSION"
else
    print_status 1 "PHP not found"
    exit 1
fi

# Check required PHP extensions
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "pdo_sqlite" "mbstring" "openssl" "tokenizer" "xml" "ctype" "json" "bcmath" "zip")
echo ""
echo "🔧 Checking PHP Extensions..."
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        print_status 0 "Extension $ext"
    else
        print_status 1 "Extension $ext missing"
    fi
done

# Check optional extensions
OPTIONAL_EXTENSIONS=("redis" "opcache" "gd" "curl")
echo ""
echo "⚡ Checking Optional Extensions..."
for ext in "${OPTIONAL_EXTENSIONS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        print_status 0 "Extension $ext (optional)"
    else
        print_warning "Extension $ext not found (optional but recommended)"
    fi
done

echo ""
echo "📁 Checking Directory Permissions..."

# Check if we're in a Laravel project
if [ ! -f "artisan" ]; then
    print_status 1 "Not in a Laravel project directory"
    exit 1
fi

print_status 0 "Laravel project detected"

# Check storage directory
if [ -d "storage" ]; then
    if [ -w "storage" ]; then
        print_status 0 "Storage directory writable"
    else
        print_status 1 "Storage directory not writable"
    fi
else
    print_status 1 "Storage directory missing"
fi

# Check bootstrap/cache directory
if [ -d "bootstrap/cache" ]; then
    if [ -w "bootstrap/cache" ]; then
        print_status 0 "Bootstrap cache directory writable"
    else
        print_status 1 "Bootstrap cache directory not writable"
    fi
else
    print_status 1 "Bootstrap cache directory missing"
fi

echo ""
echo "🔑 Checking Environment Configuration..."

# Check .env file
if [ -f ".env" ]; then
    print_status 0 ".env file exists"

    # Check APP_KEY
    if grep -q "^APP_KEY=base64:" .env; then
        print_status 0 "APP_KEY is set"
    else
        print_status 1 "APP_KEY not properly set"
    fi

    # Check database configuration
    if grep -q "^DB_CONNECTION=" .env; then
        DB_CONNECTION=$(grep "^DB_CONNECTION=" .env | cut -d '=' -f2)
        print_status 0 "Database connection: $DB_CONNECTION"
    else
        print_warning "DB_CONNECTION not set, will use default"
    fi

else
    print_status 1 ".env file missing"
fi

echo ""
echo "💾 Testing Database Connection..."

# Test database connection
if php artisan migrate:status > /dev/null 2>&1; then
    print_status 0 "Database connection successful"
else
    print_warning "Database connection failed - will use fallback"
fi

echo ""
echo "🗄️  Testing Cache System..."

# Test cache
if php artisan cache:clear > /dev/null 2>&1; then
    print_status 0 "Cache system functional"
else
    print_status 1 "Cache system failed"
fi

echo ""
echo "🧪 Running Laravel Health Checks..."

# Test artisan commands
if php artisan --version > /dev/null 2>&1; then
    LARAVEL_VERSION=$(php artisan --version)
    print_status 0 "Laravel CLI: $LARAVEL_VERSION"
else
    print_status 1 "Laravel CLI not working"
fi

# Test config loading
if php artisan config:show app.name > /dev/null 2>&1; then
    print_status 0 "Configuration loading"
else
    print_status 1 "Configuration loading failed"
fi

echo ""
echo "🚀 Production Readiness Checks..."

# Check if running in production
if [ "$APP_ENV" = "production" ]; then
    print_status 0 "Running in production mode"

    # Check if config is cached
    if [ -f "bootstrap/cache/config.php" ]; then
        print_status 0 "Configuration cached"
    else
        print_warning "Configuration not cached (run: php artisan config:cache)"
    fi

    # Check if routes are cached
    if [ -f "bootstrap/cache/routes-v7.php" ]; then
        print_status 0 "Routes cached"
    else
        print_warning "Routes not cached (run: php artisan route:cache)"
    fi

else
    print_warning "Not running in production mode (APP_ENV=$APP_ENV)"
fi

echo ""
echo "=============================================="
echo "🎯 Laravel Configuration Check Complete"
echo ""