#!/bin/bash

echo "🚀 Production Deployment Test Suite"
echo "===================================="

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Status counters
PASSED=0
FAILED=0

# Function to run test
run_test() {
    local test_name="$1"
    local test_command="$2"
    local expected_code="${3:-0}"

    echo -e "${BLUE}🧪 Testing: $test_name${NC}"

    if eval "$test_command" > /dev/null 2>&1; then
        local exit_code=$?
        if [ $exit_code -eq $expected_code ]; then
            echo -e "${GREEN}   ✅ PASSED${NC}"
            ((PASSED++))
            return 0
        else
            echo -e "${RED}   ❌ FAILED (exit code: $exit_code, expected: $expected_code)${NC}"
            ((FAILED++))
            return 1
        fi
    else
        echo -e "${RED}   ❌ FAILED (command failed)${NC}"
        ((FAILED++))
        return 1
    fi
}

# Function to test HTTP endpoint
test_endpoint() {
    local name="$1"
    local url="$2"
    local expected_code="${3:-200}"

    echo -e "${BLUE}🌐 Testing endpoint: $name${NC}"

    if command -v curl >/dev/null 2>&1; then
        local response_code=$(curl -s -o /dev/null -w "%{http_code}" "$url")
        if [ "$response_code" = "$expected_code" ]; then
            echo -e "${GREEN}   ✅ PASSED (HTTP $response_code)${NC}"
            ((PASSED++))
        else
            echo -e "${RED}   ❌ FAILED (HTTP $response_code, expected: $expected_code)${NC}"
            ((FAILED++))
        fi
    else
        echo -e "${YELLOW}   ⚠️  SKIPPED (curl not available)${NC}"
    fi
}

echo ""
echo "📋 Step 1: Laravel Core Tests"
echo "--------------------------------"

run_test "Laravel CLI available" "php artisan --version"
run_test "Configuration loading" "php artisan config:show app.name"
run_test "Database connection" "php artisan migrate:status"
run_test "Cache system" "php artisan cache:clear"
run_test "Route listing" "php artisan route:list"

echo ""
echo "🔐 Step 2: Environment Configuration Tests"
echo "-------------------------------------------"

# Test with production-like environment
export APP_ENV=production
export APP_DEBUG=false
export LOG_LEVEL=error

run_test "Production config caching" "php artisan config:cache"
run_test "Production route caching" "php artisan route:cache"
run_test "Production view caching" "php artisan view:cache"

echo ""
echo "🛡️ Step 3: Error Handling Tests"
echo "--------------------------------"

# Test fallback cache service
run_test "FallbackCacheService class exists" "php -r \"class_exists('App\\\\Services\\\\FallbackCacheService') or exit(1);\""

# Test exception handler
run_test "Exception handler exists" "php -r \"class_exists('App\\\\Exceptions\\\\Handler') or exit(1);\""

# Test health controller
run_test "Health controller exists" "php -r \"class_exists('App\\\\Http\\\\Controllers\\\\HealthController') or exit(1);\""

echo ""
echo "🧪 Step 4: API Endpoint Tests"
echo "-----------------------------"

# Start a test server in background for API testing
if command -v php >/dev/null 2>&1; then
    echo "Starting test server..."
    php artisan serve --host=127.0.0.1 --port=8001 &
    SERVER_PID=$!
    sleep 3

    # Test API endpoints
    test_endpoint "Health check (simple)" "http://127.0.0.1:8001/health-check.php" "200"
    test_endpoint "Health check (Laravel)" "http://127.0.0.1:8001/health" "200"
    test_endpoint "Home page" "http://127.0.0.1:8001/" "200"

    # Test API with proper headers
    echo -e "${BLUE}🌐 Testing: API URL creation${NC}"
    if response=$(curl -s -w "%{http_code}" -H "X-Device-ID: test-deploy" -H "Content-Type: application/json" -d '{"url":"https://example.com/test-deploy"}' "http://127.0.0.1:8001/api/urls"); then
        http_code="${response: -3}"
        if [ "$http_code" = "201" ]; then
            echo -e "${GREEN}   ✅ PASSED (HTTP $http_code)${NC}"
            ((PASSED++))
        else
            echo -e "${RED}   ❌ FAILED (HTTP $http_code)${NC}"
            echo "Response: ${response%???}"
            ((FAILED++))
        fi
    else
        echo -e "${RED}   ❌ FAILED (curl error)${NC}"
        ((FAILED++))
    fi

    # Stop test server
    kill $SERVER_PID 2>/dev/null || true
    wait $SERVER_PID 2>/dev/null || true
fi

echo ""
echo "🐳 Step 5: Docker Configuration Tests"
echo "-------------------------------------"

if [ -f "Dockerfile" ]; then
    run_test "Dockerfile exists" "test -f Dockerfile"
    run_test "Entrypoint script exists" "test -f docker/entrypoint.sh"
    run_test "Entrypoint is executable" "test -x docker/entrypoint.sh"
    run_test "PHP-FPM config exists" "test -f docker/php/php-fpm.conf"
    run_test "Nginx config exists" "test -f docker/nginx/default.conf"
else
    echo -e "${YELLOW}   ⚠️  Docker files not found${NC}"
fi

echo ""
echo "🧹 Step 6: Cleanup Tests"
echo "------------------------"

run_test "Clear all caches" "php artisan optimize:clear"
run_test "Cache rebuilt successfully" "php artisan config:cache && php artisan route:cache"

echo ""
echo "===================================="
echo "📊 Test Results Summary"
echo "===================================="
echo -e "${GREEN}✅ Passed: $PASSED${NC}"
echo -e "${RED}❌ Failed: $FAILED${NC}"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 All tests passed! Ready for production deployment.${NC}"
    exit 0
else
    echo -e "${RED}💥 Some tests failed. Please fix issues before deploying.${NC}"
    exit 1
fi