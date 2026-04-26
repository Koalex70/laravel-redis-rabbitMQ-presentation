<?php

namespace App\Services\Metrics;

use Illuminate\Support\Facades\Redis;

class ApiLatencyMetricsService
{
    public function record(string $endpoint, int $durationMs): void
    {
        $safeEndpoint = str_replace(['/', ':', ' '], '_', $endpoint);
        Redis::incr("metrics:api:{$safeEndpoint}:count");
        Redis::incrbyfloat("metrics:api:{$safeEndpoint}:sum_ms", (float) $durationMs);
    }

    public function snapshot(array $endpoints): array
    {
        $result = [];

        foreach ($endpoints as $endpoint) {
            $safeEndpoint = str_replace(['/', ':', ' '], '_', $endpoint);
            $count = (int) Redis::get("metrics:api:{$safeEndpoint}:count");
            $sumMs = (float) Redis::get("metrics:api:{$safeEndpoint}:sum_ms");
            $avgMs = $count > 0 ? round($sumMs / $count, 2) : 0.0;

            $result[$endpoint] = [
                'count' => $count,
                'sum_ms' => round($sumMs, 2),
                'avg_ms' => $avgMs,
            ];
        }

        return $result;
    }

    public function reset(array $endpoints): void
    {
        foreach ($endpoints as $endpoint) {
            $safeEndpoint = str_replace(['/', ':', ' '], '_', $endpoint);
            Redis::del("metrics:api:{$safeEndpoint}:count");
            Redis::del("metrics:api:{$safeEndpoint}:sum_ms");
        }
    }
}
