<?php

use App\Http\Controllers\Api\UrlController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', [App\Http\Controllers\HealthController::class, 'check']);

Route::get('/{code}', [UrlController::class, 'show'])->where('code', '[A-HJ-KM-NP-Z2-9]+');
