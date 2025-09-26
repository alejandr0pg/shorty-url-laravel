<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Handle database connection errors gracefully
        if ($this->isDatabaseConnectionError($e)) {
            Log::error('Database connection error', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Service temporarily unavailable',
                    'message' => 'Database connection issue',
                    'timestamp' => now()->toISOString(),
                ], 503);
            }

            return response()->view('errors.503', [], 503);
        }

        // Handle Redis connection errors
        if ($this->isRedisConnectionError($e)) {
            Log::warning('Redis connection error', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
            ]);

            // Continue without Redis if it's not critical
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Cache temporarily unavailable',
                    'message' => 'Service degraded but functional',
                    'timestamp' => now()->toISOString(),
                ], 200);
            }
        }

        return parent::render($request, $e);
    }

    private function isDatabaseConnectionError(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'Connection refused') ||
               str_contains($e->getMessage(), 'SQLSTATE') ||
               str_contains($e->getMessage(), 'Connection timed out') ||
               str_contains($e->getMessage(), 'Access denied');
    }

    private function isRedisConnectionError(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'Redis') ||
               str_contains($e->getMessage(), 'Connection refused') &&
               str_contains($e->getMessage(), '6379');
    }
}
