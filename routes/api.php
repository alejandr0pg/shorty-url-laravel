<?php

use App\Http\Controllers\Api\UrlController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::apiResource('urls', UrlController::class);
    Route::get('resolve/{code}', [UrlController::class, 'resolve'])->where('code', '[A-HJ-KM-NP-Z2-9]+');
});
