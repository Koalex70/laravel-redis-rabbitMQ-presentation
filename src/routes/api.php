<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'app']);
    Route::get('/health/deps', [HealthController::class, 'dependencies']);
    Route::get('/products/{id}', [ProductController::class, 'show'])->whereNumber('id');
});
