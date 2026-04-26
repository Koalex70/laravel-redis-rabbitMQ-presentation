<?php

use App\Http\Controllers\DemoController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'app']);
    Route::get('/health/deps', [HealthController::class, 'dependencies']);
    Route::get('/products/{id}', [ProductController::class, 'show'])->whereNumber('id');
    Route::post('/jobs/report', [JobController::class, 'store']);
    Route::post('/jobs/report/bulk', [JobController::class, 'bulk']);
    Route::get('/jobs/{id}', [JobController::class, 'show'])->whereUuid('id');

    Route::get('/metrics/cache', [MetricsController::class, 'cache']);
    Route::get('/metrics/queue', [MetricsController::class, 'queue']);
    Route::get('/metrics/overview', [MetricsController::class, 'overview']);
    Route::get('/metrics/prometheus', [MetricsController::class, 'prometheus']);

    Route::post('/demo/cache/flush', [DemoController::class, 'flushCache']);
    Route::post('/demo/metrics/reset', [DemoController::class, 'resetMetrics']);
    Route::post('/demo/jobs/enqueue', [DemoController::class, 'enqueueDemoJobs']);

    Route::post('/tests/cache/run', [TestController::class, 'runCache']);
    Route::post('/tests/jobs/run', [TestController::class, 'runJobs']);
    Route::get('/tests/runs', [TestController::class, 'history']);
    Route::get('/tests/runs/{id}', [TestController::class, 'show'])->whereUuid('id');
});
