<?php

use App\Http\Controllers\Api\UrlController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', [App\Http\Controllers\HealthController::class, 'check']);

// API Documentation routes
Route::group(['prefix' => 'docs', 'middleware' => []], function () {
    Route::get('/', function () {
        // Verify the view exists before rendering
        if (! view()->exists('scribe.index')) {
            abort(503, 'API documentation is not available. Please contact support.');
        }

        return view('scribe.index');
    })->name('scribe');

    Route::get('/postman', function () {
        $path = storage_path('app/private/scribe/collection.json');

        if (! file_exists($path)) {
            abort(503, 'API documentation (Postman collection) is not available. Please contact support.');
        }

        $collection = file_get_contents($path);

        return response($collection, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="api-collection.json"',
        ]);
    })->name('scribe.postman');

    Route::get('/openapi', function () {
        $path = storage_path('app/private/scribe/openapi.yaml');

        if (! file_exists($path)) {
            abort(503, 'API documentation (OpenAPI spec) is not available. Please contact support.');
        }

        $openapi = file_get_contents($path);

        return response($openapi, 200, [
            'Content-Type' => 'application/x-yaml',
            'Content-Disposition' => 'attachment; filename="openapi.yaml"',
        ]);
    })->name('scribe.openapi');
});

Route::get('/{code}', [UrlController::class, 'show'])->where('code', '[A-HJ-KM-NP-Z2-9]+');
