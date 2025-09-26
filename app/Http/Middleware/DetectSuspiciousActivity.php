<?php

namespace App\Http\Middleware;

use App\Services\LoggingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class DetectSuspiciousActivity
{
    private const RATE_LIMIT_WINDOW = 300; // 5 minutes
    private const SUSPICIOUS_REQUEST_THRESHOLD = 50; // requests per window
    private const HIGH_FREQUENCY_THRESHOLD = 20; // requests per minute

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $loggingService = new LoggingService();
        $deviceId = $request->header('X-Device-ID');
        $ip = $request->ip();

        if ($deviceId) {
            $this->checkHighFrequencyActivity($deviceId, $loggingService);
            $this->checkSuspiciousPatterns($request, $deviceId, $loggingService);
        }

        $this->trackRequestRate($ip, $deviceId);

        return $next($request);
    }

    /**
     * Check for high frequency activity from a single device
     */
    private function checkHighFrequencyActivity(string $deviceId, LoggingService $loggingService): void
    {
        $key = "device_activity:{$deviceId}";
        $requests = Cache::get($key, []);
        $now = time();

        // Clean old requests (older than 1 minute)
        $requests = array_filter($requests, fn($timestamp) => $now - $timestamp < 60);

        if (count($requests) > self::HIGH_FREQUENCY_THRESHOLD) {
            $loggingService->logHighFrequencyActivity($deviceId, count($requests), 60);
        }

        // Add current request
        $requests[] = $now;
        Cache::put($key, $requests, 120); // Keep for 2 minutes
    }

    /**
     * Check for suspicious URL patterns
     */
    private function checkSuspiciousPatterns(Request $request, string $deviceId, LoggingService $loggingService): void
    {
        if ($request->isMethod('POST') && $request->has('url')) {
            $url = $request->input('url');

            // Check for suspicious patterns
            $suspiciousPatterns = [
                'javascript:' => 'JavaScript scheme detected',
                'data:' => 'Data URI detected',
                'file:' => 'File scheme detected',
                'vbscript:' => 'VBScript scheme detected',
                '<script' => 'Script tag in URL',
                'eval(' => 'JavaScript eval detected',
                'document.cookie' => 'Cookie access attempt',
                'localStorage' => 'LocalStorage access attempt'
            ];

            foreach ($suspiciousPatterns as $pattern => $reason) {
                if (stripos($url, $pattern) !== false) {
                    $loggingService->logSuspiciousActivity($url, $deviceId, $reason);
                }
            }

            // Check for extremely long URLs (potential DoS)
            if (strlen($url) > 4000) {
                $loggingService->logSuspiciousActivity($url, $deviceId, 'Extremely long URL detected');
            }

            // Check for URL with many special characters (potential encoding attack)
            $specialCharCount = strlen($url) - strlen(preg_replace('/[^a-zA-Z0-9]/', '', $url));
            if ($specialCharCount > strlen($url) * 0.7) { // More than 70% special chars
                $loggingService->logSuspiciousActivity($url, $deviceId, 'High special character ratio');
            }
        }
    }

    /**
     * Track overall request rate per IP and device
     */
    private function trackRequestRate(string $ip, ?string $deviceId): void
    {
        // Track by IP
        $ipKey = "rate_limit:ip:{$ip}";
        $ipRequests = Cache::get($ipKey, 0);
        Cache::put($ipKey, $ipRequests + 1, self::RATE_LIMIT_WINDOW);

        // Track by device if available
        if ($deviceId) {
            $deviceKey = "rate_limit:device:{$deviceId}";
            $deviceRequests = Cache::get($deviceKey, 0);
            Cache::put($deviceKey, $deviceRequests + 1, self::RATE_LIMIT_WINDOW);

            // Log if suspicious rate detected
            if ($deviceRequests > self::SUSPICIOUS_REQUEST_THRESHOLD) {
                $loggingService = new LoggingService();
                $loggingService->logDeviceIdIssue($deviceId, 'High request rate detected', [
                    'request_count' => $deviceRequests,
                    'time_window' => self::RATE_LIMIT_WINDOW
                ]);
            }
        }
    }
}