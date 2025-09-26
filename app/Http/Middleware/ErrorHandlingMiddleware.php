<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorHandlingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            // Log the error for debugging
            Log::error('Application Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);

            // Return appropriate error response based on request type
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Internal server error',
                    'message' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
                    'timestamp' => now()->toISOString(),
                ], 500);
            }

            // For non-JSON requests, return a simple error page
            return response()->view('errors.500', [
                'message' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }
}
