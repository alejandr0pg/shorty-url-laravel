<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Service for logging edge cases and monitoring system behavior
 */
class LoggingService
{
    /**
     * Log URL validation edge cases
     */
    public function logUrlValidationEdgeCase(string $url, array $validationResult, string $context = 'general'): void
    {
        if (!$validationResult['valid']) {
            Log::warning('URL Validation Edge Case', [
                'context' => $context,
                'original_url' => $url,
                'errors' => $validationResult['errors'],
                'sanitized_url' => $this->getSanitizedUrl($url),
                'timestamp' => now(),
                'user_agent' => request()->userAgent(),
                'ip' => request()->ip(),
            ]);
        }
    }

    /**
     * Log RFC 1738 processing anomalies
     */
    public function logRfc1738ProcessingAnomaly(string $originalUrl, string $processedUrl, array $processing): void
    {
        Log::info('RFC 1738 Processing', [
            'original_url' => $originalUrl,
            'processed_url' => $processedUrl,
            'was_sanitized' => $processing['sanitized'] ?? false,
            'was_normalized' => $processing['normalized'] ?? false,
            'processing_time' => microtime(true) - (request()->server('REQUEST_TIME_FLOAT') ?? microtime(true)),
            'timestamp' => now(),
        ]);
    }

    /**
     * Log suspicious URL shortening attempts
     */
    public function logSuspiciousActivity(string $url, string $deviceId, string $reason): void
    {
        Log::warning('Suspicious URL Shortening Activity', [
            'url' => $url,
            'device_id' => $deviceId,
            'reason' => $reason,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Log high frequency requests from single device
     */
    public function logHighFrequencyActivity(string $deviceId, int $requestCount, int $timeWindow): void
    {
        Log::warning('High Frequency Activity Detected', [
            'device_id' => $deviceId,
            'request_count' => $requestCount,
            'time_window_seconds' => $timeWindow,
            'ip' => request()->ip(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Log performance metrics for slow operations
     */
    public function logPerformanceMetrics(string $operation, float $duration, array $metadata = []): void
    {
        if ($duration > 1.0) { // Log operations slower than 1 second
            Log::warning('Slow Operation Detected', [
                'operation' => $operation,
                'duration_seconds' => $duration,
                'metadata' => $metadata,
                'timestamp' => now(),
            ]);
        }
    }

    /**
     * Log cache-related issues
     */
    public function logCacheIssue(string $cacheKey, string $operation, string $issue): void
    {
        Log::warning('Cache Operation Issue', [
            'cache_key' => $cacheKey,
            'operation' => $operation,
            'issue' => $issue,
            'timestamp' => now(),
        ]);
    }

    /**
     * Log failed redirections
     */
    public function logFailedRedirection(string $shortCode, string $reason): void
    {
        Log::error('Failed Redirection', [
            'short_code' => $shortCode,
            'reason' => $reason,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referer' => request()->header('referer'),
            'timestamp' => now(),
        ]);
    }

    /**
     * Log device ID related issues
     */
    public function logDeviceIdIssue(string $deviceId, string $issue, array $context = []): void
    {
        Log::warning('Device ID Issue', [
            'device_id' => $deviceId,
            'issue' => $issue,
            'context' => $context,
            'ip' => request()->ip(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Log successful operations with metrics
     */
    public function logSuccessfulOperation(string $operation, array $metrics = []): void
    {
        Log::info('Operation Success', [
            'operation' => $operation,
            'metrics' => $metrics,
            'timestamp' => now(),
        ]);
    }

    /**
     * Helper to safely get sanitized URL for logging
     */
    private function getSanitizedUrl(string $url): string
    {
        try {
            $urlValidator = new UrlValidatorService();
            return $urlValidator->sanitizeUrl($url);
        } catch (\Exception $e) {
            return '[Unable to sanitize: ' . $e->getMessage() . ']';
        }
    }

    /**
     * Log system health metrics
     */
    public function logSystemHealth(): void
    {
        Log::info('System Health Check', [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'total_urls' => \App\Models\Url::count(),
            'active_devices' => \App\Models\Url::distinct('device_id')->count(),
            'timestamp' => now(),
        ]);
    }
}