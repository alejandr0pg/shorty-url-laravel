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
        return view('scribe.index');
    })->name('scribe');

    Route::get('/postman', function () {
        $collection = file_get_contents(storage_path('app/private/scribe/collection.json'));

        return response($collection, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="api-collection.json"',
        ]);
    })->name('scribe.postman');

    Route::get('/openapi', function () {
        $openapi = file_get_contents(storage_path('app/private/scribe/openapi.yaml'));

        return response($openapi, 200, [
            'Content-Type' => 'application/x-yaml',
            'Content-Disposition' => 'attachment; filename="openapi.yaml"',
        ]);
    })->name('scribe.openapi');
});

Route::get('/{code}', [UrlController::class, 'show'])->where('code', '[A-HJ-KM-NP-Z2-9]+');
