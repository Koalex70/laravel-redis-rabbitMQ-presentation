<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthReport
{
    public static function app(): array
    {
        return [
            'status' => 'ok',
            'app' => config('app.name'),
            'environment' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public static function dependencies(): array
    {
        $dbStartedAt = microtime(true);
        $dbOk = true;
        $dbError = null;

        try {
            DB::select('select 1');
        } catch (\Throwable $exception) {
            $dbOk = false;
            $dbError = $exception->getMessage();
        }

        $dbLatencyMs = (int) round((microtime(true) - $dbStartedAt) * 1000);

        $redisStartedAt = microtime(true);
        $redisOk = true;
        $redisError = null;

        try {
            Redis::command('ping');
        } catch (\Throwable $exception) {
            $redisOk = false;
            $redisError = $exception->getMessage();
        }

        $redisLatencyMs = (int) round((microtime(true) - $redisStartedAt) * 1000);
        $status = ($dbOk && $redisOk) ? 'ok' : 'degraded';

        return [
            'status' => $status,
            'checks' => [
                'db' => [
                    'status' => $dbOk ? 'up' : 'down',
                    'latency_ms' => $dbLatencyMs,
                    'error' => $dbError,
                ],
                'redis' => [
                    'status' => $redisOk ? 'up' : 'down',
                    'latency_ms' => $redisLatencyMs,
                    'error' => $redisError,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
