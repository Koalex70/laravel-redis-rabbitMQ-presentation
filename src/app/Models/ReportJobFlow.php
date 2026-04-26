<?php

namespace App\Models;

use App\Services\Metrics\ApiLatencyMetricsService;
use App\Services\Queue\ReportJobQueueService;

class ReportJobFlow
{
    public static function enqueueOne(
        ReportJobQueueService $queueService,
        ApiLatencyMetricsService $latencyMetrics,
        array $payload,
    ): array {
        $startedAt = microtime(true);
        $job = $queueService->enqueue($payload);
        $latencyMetrics->record('jobs_store', (int) round((microtime(true) - $startedAt) * 1000));

        return [
            'job' => $job,
            'queue_depth' => $queueService->queueDepth(),
        ];
    }

    public static function enqueueBulk(
        ReportJobQueueService $queueService,
        ApiLatencyMetricsService $latencyMetrics,
        int $count,
        array $payload,
    ): array {
        $startedAt = microtime(true);
        $jobs = [];

        for ($i = 0; $i < $count; $i++) {
            $jobs[] = $queueService->enqueue($payload);
        }

        $latencyMetrics->record('jobs_bulk', (int) round((microtime(true) - $startedAt) * 1000));

        return [
            'jobs' => $jobs,
            'count' => $count,
            'queue_depth' => $queueService->queueDepth(),
        ];
    }

    public static function findById(
        ApiLatencyMetricsService $latencyMetrics,
        string $id,
    ): ?ReportJob {
        $startedAt = microtime(true);
        $job = ReportJob::query()->find($id);
        $latencyMetrics->record('jobs_show', (int) round((microtime(true) - $startedAt) * 1000));

        return $job;
    }
}
