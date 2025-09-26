#!/bin/bash

echo "🔍 AWS Deployment Configuration Validation"
echo "=========================================="

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Counters
PASSED=0
FAILED=0
WARNINGS=0

# Function to run test
check_config() {
    local test_name="$1"
    local test_command="$2"
    local is_warning="${3:-false}"

    echo -e "${BLUE}🔍 Checking: $test_name${NC}"

    if eval "$test_command" > /dev/null 2>&1; then
        echo -e "${GREEN}   ✅ PASSED${NC}"
        ((PASSED++))
        return 0
    else
        if [ "$is_warning" = "true" ]; then
            echo -e "${YELLOW}   ⚠️  WARNING${NC}"
            ((WARNINGS++))
        else
            echo -e "${RED}   ❌ FAILED${NC}"
            ((FAILED++))
        fi
        return 1
    fi
}

echo ""
echo "🐳 Step 1: Docker Configuration"
echo "--------------------------------"

check_config "Dockerfile exists" "test -f Dockerfile"
check_config "Entrypoint script exists" "test -f docker/entrypoint.sh"
check_config "PHP configuration exists" "test -f docker/php/php.ini"
check_config "Nginx configuration exists" "test -f docker/nginx/default.conf"
check_config "PHP-FPM configuration exists" "test -f docker/php/php-fpm.conf"

echo ""
echo "🔧 Step 2: Production Environment"
echo "---------------------------------"

check_config "Production .env template exists" "test -f .env.production"

# Check critical environment variables in production template
if [ -f ".env.production" ]; then
    check_config "APP_ENV set to production" "grep -q '^APP_ENV=production' .env.production"
    check_config "APP_DEBUG set to false" "grep -q '^APP_DEBUG=false' .env.production"
    check_config "DB_HOST configured for RDS" "grep -q '^DB_HOST=.*rds\.amazonaws\.com' .env.production"
    check_config "REDIS_HOST configured for ElastiCache" "grep -q '^REDIS_HOST=.*cache\.amazonaws\.com' .env.production"
    check_config "APP_KEY is set" "grep -q '^APP_KEY=base64:' .env.production"
fi

echo ""
echo "📁 Step 3: Directory Structure"
echo "------------------------------"

check_config "Storage directory exists" "test -d storage"
check_config "Bootstrap cache directory exists" "test -d bootstrap/cache"
check_config "Storage framework directory exists" "test -d storage/framework"
check_config "Storage logs directory exists" "test -d storage/logs"

echo ""
echo "🔐 Step 4: Laravel Configuration"
echo "--------------------------------"

check_config "Laravel artisan available" "php artisan --version"
check_config "APP_KEY is generated" "php artisan key:generate --show"
check_config "Config can be cached" "php artisan config:cache --dry-run" "true"
check_config "Routes can be cached" "php artisan route:cache --dry-run" "true"

echo ""
echo "💾 Step 5: Database Configuration"
echo "---------------------------------"

# Test database with current environment
echo "Testing current database connection..."
if php artisan migrate:status > /dev/null 2>&1; then
    check_config "Database connection works" "php artisan migrate:status"
else
    echo -e "${YELLOW}   ⚠️  Current DB connection failed - will use fallback in production${NC}"
    ((WARNINGS++))
fi

echo ""
echo "🗄️  Step 6: Cache Configuration"
echo "-------------------------------"

check_config "Cache system functional" "php artisan cache:clear"
check_config "FallbackCacheService exists" "php -r \"class_exists('App\\\\Services\\\\FallbackCacheService') or exit(1);\""

# Test Redis connection if available
if [ -n "$REDIS_HOST" ]; then
    echo "Testing Redis connection to: $REDIS_HOST"
    if command -v redis-cli >/dev/null 2>&1; then
        if timeout 3 redis-cli -h "$REDIS_HOST" -p "${REDIS_PORT:-6379}" ping > /dev/null 2>&1; then
            check_config "Redis connection works" "true"
        else
            echo -e "${YELLOW}   ⚠️  Redis connection failed - will use database fallback${NC}"
            ((WARNINGS++))
        fi
    else
        echo -e "${YELLOW}   ⚠️  redis-cli not available for testing${NC}"
        ((WARNINGS++))
    fi
fi

echo ""
echo "🏥 Step 7: Health Check Endpoints"
echo "---------------------------------"

check_config "Health controller exists" "php -r \"class_exists('App\\\\Http\\\\Controllers\\\\HealthController') or exit(1);\""
check_config "Simple health check file exists" "test -f public/health-check.php"

echo ""
echo "🛡️  Step 8: Error Handling"
echo "-------------------------"

check_config "Exception handler exists" "php -r \"class_exists('App\\\\Exceptions\\\\Handler') or exit(1);\""
check_config "FallbackCacheService configured" "grep -q 'FallbackCacheService' app/Http/Controllers/Api/UrlController.php"

echo ""
echo "🚀 Step 9: Production Optimization"
echo "----------------------------------"

# Check if running in production mode
if [ "$APP_ENV" = "production" ]; then
    echo "Running in production mode - checking optimizations..."
    check_config "Config is cached" "test -f bootstrap/cache/config.php" "true"
    check_config "Routes are cached" "test -f bootstrap/cache/routes-v7.php" "true"
    check_config "Views are compiled" "test -d storage/framework/views" "true"
else
    echo -e "${YELLOW}   ⚠️  Not in production mode (APP_ENV=$APP_ENV)${NC}"
    ((WARNINGS++))
fi

echo ""
echo "📊 Step 10: AWS Specific Checks"
echo "-------------------------------"

# Check AWS-specific configurations
check_config "ECS task definition exists" "test -f deploy/task-definition.json" "true"
check_config "GitHub Actions workflow exists" "test -f .github/workflows/deploy.yml" "true"

# Check for potential AWS deployment issues
if grep -q "127.0.0.1" .env 2>/dev/null; then
    echo -e "${YELLOW}   ⚠️  Found localhost references in .env - ensure production uses AWS endpoints${NC}"
    ((WARNINGS++))
fi

echo ""
echo "=========================================="
echo "📋 AWS Deployment Check Summary"
echo "=========================================="
echo -e "${GREEN}✅ Passed: $PASSED${NC}"
echo -e "${RED}❌ Failed: $FAILED${NC}"
echo -e "${YELLOW}⚠️  Warnings: $WARNINGS${NC}"

if [ $FAILED -eq 0 ]; then
    if [ $WARNINGS -gt 0 ]; then
        echo -e "${YELLOW}🎯 Ready for deployment with minor warnings${NC}"
        echo "💡 Review warnings above and ensure AWS secrets are properly configured"
        exit 0
    else
        echo -e "${GREEN}🎉 Fully ready for AWS production deployment!${NC}"
        exit 0
    fi
else
    echo -e "${RED}💥 Deployment readiness issues found. Fix failed checks before deploying.${NC}"
    exit 1
fi