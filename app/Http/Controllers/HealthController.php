<?php

namespace App\Http\Controllers;

use App\Services\FallbackCacheService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'services' => [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
                'php' => $this->checkPhp(),
            ],
        ];

        $overallHealth = $this->determineOverallHealth($checks['services']);
        $checks['status'] = $overallHealth['status'];

        $statusCode = $overallHealth['status'] === 'healthy' ? 200 : 503;

        return response()->json($checks, $statusCode);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $version = DB::select('SELECT VERSION() as version')[0]->version;

            return [
                'status' => 'healthy',
                'version' => $version,
                'connection' => config('database.default'),
            ];
        } catch (Exception $e) {
            Log::error('Database health check failed', ['error' => $e->getMessage()]);

            return [
                'status' => 'unhealthy',
                'error' => 'Connection failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Database unavailable',
            ];
        }
    }

    private function checkRedis(): array
    {
        try {
            $redis = Cache::store('redis');
            $testKey = 'health_check_'.time();
            $redis->put($testKey, 'test', 5);
            $value = $redis->get($testKey);
            $redis->forget($testKey);

            if ($value !== 'test') {
                throw new Exception('Redis read/write test failed');
            }

            return [
                'status' => 'healthy',
                'host' => config('database.redis.default.host'),
                'port' => config('database.redis.default.port'),
            ];
        } catch (Exception $e) {
            Log::warning('Redis health check failed', ['error' => $e->getMessage()]);

            return [
                'status' => 'degraded',
                'error' => 'Connection failed',
                'fallback' => 'Database cache available',
                'message' => config('app.debug') ? $e->getMessage() : 'Redis unavailable',
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_cache_'.time();
            $testValue = 'cache_test_'.rand(1000, 9999);

            // Test fallback cache service
            FallbackCacheService::put($testKey, $testValue, 5);
            $retrieved = Cache::get($testKey);
            FallbackCacheService::forget($testKey);

            if ($retrieved !== $testValue) {
                throw new Exception('Cache read/write test failed');
            }

            return [
                'status' => 'healthy',
                'driver' => config('cache.default'),
                'fallback' => 'Available',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Cache test failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Cache unavailable',
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $paths = [
                'storage' => storage_path(),
                'logs' => storage_path('logs'),
                'cache' => storage_path('framework/cache'),
                'sessions' => storage_path('framework/sessions'),
                'views' => storage_path('framework/views'),
            ];

            $issues = [];
            foreach ($paths as $name => $path) {
                if (! is_dir($path)) {
                    $issues[] = "Missing directory: {$name}";
                } elseif (! is_writable($path)) {
                    $issues[] = "Not writable: {$name}";
                }
            }

            if (! empty($issues)) {
                return [
                    'status' => 'unhealthy',
                    'issues' => $issues,
                ];
            }

            return [
                'status' => 'healthy',
                'disk_usage' => disk_free_space(storage_path()),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Storage check failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Storage issues detected',
            ];
        }
    }

    private function checkPhp(): array
    {
        $requiredExtensions = ['pdo', 'pdo_mysql', 'redis', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json'];
        $missingExtensions = [];

        foreach ($requiredExtensions as $extension) {
            if (! extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }

        return [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'missing_extensions' => $missingExtensions,
            'status' => empty($missingExtensions) ? 'healthy' : 'unhealthy',
        ];
    }

    private function determineOverallHealth(array $services): array
    {
        $unhealthyServices = [];
        $degradedServices = [];

        foreach ($services as $service => $status) {
            if ($status['status'] === 'unhealthy') {
                $unhealthyServices[] = $service;
            } elseif ($status['status'] === 'degraded') {
                $degradedServices[] = $service;
            }
        }

        if (! empty($unhealthyServices)) {
            return [
                'status' => 'unhealthy',
                'issues' => $unhealthyServices,
            ];
        }

        if (! empty($degradedServices)) {
            return [
                'status' => 'degraded',
                'warnings' => $degradedServices,
            ];
        }

        return ['status' => 'healthy'];
    }
}
