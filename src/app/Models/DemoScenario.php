<?php

namespace App\Models;

use App\Services\Metrics\ApiLatencyMetricsService;
use App\Services\ProductCacheService;
use App\Services\Queue\ReportJobQueueService;
use Illuminate\Support\Facades\Redis;

class DemoScenario
{
    public static function flushCache(ProductCacheService $productCacheService): array
    {
        return [
            'deleted_keys' => $productCacheService->flushAll(),
        ];
    }

    public static function resetMetrics(
        ReportJobQueueService $queueService,
        ApiLatencyMetricsService $latencyMetrics,
    ): array {
        Redis::del('metrics:cache:hits');
        Redis::del('metrics:cache:misses');
        $queueService->resetMetrics();
        $latencyMetrics->reset(MetricsSnapshot::metricEndpoints());

        return ['status' => 'ok'];
    }

    public static function enqueueDemoJobs(
        ReportJobQueueService $queueService,
        int $count,
        bool $forceFail,
    ): array {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $job = $queueService->enqueue([
                'report_type' => 'demo',
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
                'user_id' => 1,
                'force_fail' => $forceFail,
            ]);

            $ids[] = $job->id;
        }

        return [
            'count' => $count,
            'job_ids' => $ids,
            'queue_depth' => $queueService->queueDepth(),
        ];
    }
}
