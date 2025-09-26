<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FallbackCacheService
{
    /**
     * @template TCacheValue
     *
     * @param  callable(): TCacheValue  $callback
     * @return TCacheValue
     */
    public static function remember(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, callable $callback): mixed
    {
        try {
            // Try Redis first
            /** @var TCacheValue */
            return Cache::store('redis')->remember($key, $ttl, $callback);
        } catch (Exception $e) {
            Log::warning('Redis cache failed, falling back to database cache', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            try {
                // Fallback to database cache
                /** @var TCacheValue */
                return Cache::store('database')->remember($key, $ttl, $callback);
            } catch (Exception $e2) {
                Log::error('All cache stores failed, executing callback directly', [
                    'key' => $key,
                    'redis_error' => $e->getMessage(),
                    'database_error' => $e2->getMessage(),
                ]);

                // If all cache fails, execute callback directly
                return $callback();
            }
        }
    }

    public static function forget(string $key): bool
    {
        $success = true;

        try {
            Cache::store('redis')->forget($key);
        } catch (Exception $e) {
            Log::warning('Failed to clear Redis cache', ['key' => $key, 'error' => $e->getMessage()]);
            $success = false;
        }

        try {
            Cache::store('database')->forget($key);
        } catch (Exception $e) {
            Log::warning('Failed to clear database cache', ['key' => $key, 'error' => $e->getMessage()]);
            $success = false;
        }

        return $success;
    }

    public static function put(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        try {
            return Cache::store('redis')->put($key, $value, $ttl);
        } catch (Exception $e) {
            Log::warning('Redis cache put failed, falling back to database cache', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            try {
                return Cache::store('database')->put($key, $value, $ttl);
            } catch (Exception $e2) {
                Log::error('All cache stores failed for put operation', [
                    'key' => $key,
                    'redis_error' => $e->getMessage(),
                    'database_error' => $e2->getMessage(),
                ]);

                return false;
            }
        }
    }
}
