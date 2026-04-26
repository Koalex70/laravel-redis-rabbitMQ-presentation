<?php

namespace App\Models;

use App\Services\Metrics\ApiLatencyMetricsService;
use App\Services\Queue\ReportJobQueueService;
use App\Support\ReportJobStatus;
use Illuminate\Support\Facades\Redis;

class MetricsSnapshot
{
    public static function cache(): array
    {
        $hits = (int) Redis::get('metrics:cache:hits');
        $misses = (int) Redis::get('metrics:cache:misses');
        $total = $hits + $misses;

        return [
            'hits_total' => $hits,
            'misses_total' => $misses,
            'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 2) : 0.0,
            'cache_keys_count' => count((array) Redis::keys('cache:product:*')),
        ];
    }

    public static function queue(ReportJobQueueService $queueService): array
    {
        return [
            'queue_name' => $queueService->queueNamePublic(),
            'queue_depth' => $queueService->queueDepth(),
            'dead_letter_depth' => $queueService->deadLetterDepth(),
            'jobs' => $queueService->metricsSnapshot(),
            'job_status_counts' => [
                ReportJobStatus::QUEUED => ReportJob::query()->where('status', ReportJobStatus::QUEUED)->count(),
                ReportJobStatus::PROCESSING => ReportJob::query()->where('status', ReportJobStatus::PROCESSING)->count(),
                ReportJobStatus::DONE => ReportJob::query()->where('status', ReportJobStatus::DONE)->count(),
                ReportJobStatus::FAILED => ReportJob::query()->where('status', ReportJobStatus::FAILED)->count(),
            ],
        ];
    }

    public static function overview(
        ReportJobQueueService $queueService,
        ApiLatencyMetricsService $latencyMetrics,
    ): array {
        $cache = self::cache();

        return [
            'cache' => [
                'hits_total' => $cache['hits_total'],
                'misses_total' => $cache['misses_total'],
                'hit_rate' => $cache['hit_rate'],
            ],
            'queue' => [
                'depth' => $queueService->queueDepth(),
                'dead_letter_depth' => $queueService->deadLetterDepth(),
                'jobs' => $queueService->metricsSnapshot(),
            ],
            'api_latency' => $latencyMetrics->snapshot(self::metricEndpoints()),
        ];
    }

    public static function prometheus(
        ReportJobQueueService $queueService,
        ApiLatencyMetricsService $latencyMetrics,
    ): string {
        $cache = self::cache();
        $queue = $queueService->metricsSnapshot();
        $latency = $latencyMetrics->snapshot(self::metricEndpoints());

        $lines = [
            '# HELP cache_hits_total Total cache hits.',
            '# TYPE cache_hits_total counter',
            "cache_hits_total {$cache['hits_total']}",
            '# HELP cache_misses_total Total cache misses.',
            '# TYPE cache_misses_total counter',
            "cache_misses_total {$cache['misses_total']}",
            '# HELP queue_depth Current queue depth.',
            '# TYPE queue_depth gauge',
            "queue_depth ".$queueService->queueDepth(),
            '# HELP dead_letter_depth Current dead-letter queue depth.',
            '# TYPE dead_letter_depth gauge',
            "dead_letter_depth ".$queueService->deadLetterDepth(),
            '# HELP jobs_enqueued_total Total enqueued jobs.',
            '# TYPE jobs_enqueued_total counter',
            "jobs_enqueued_total {$queue['enqueued_total']}",
            '# HELP jobs_processed_total Total processed jobs.',
            '# TYPE jobs_processed_total counter',
            "jobs_processed_total {$queue['processed_total']}",
            '# HELP jobs_failed_total Total failed jobs.',
            '# TYPE jobs_failed_total counter',
            "jobs_failed_total {$queue['failed_total']}",
            '# HELP jobs_retried_total Total retried jobs.',
            '# TYPE jobs_retried_total counter',
            "jobs_retried_total {$queue['retried_total']}",
        ];

        foreach ($latency as $endpoint => $item) {
            $name = str_replace(['/', ':', ' '], '_', $endpoint);
            $lines[] = "# HELP api_latency_avg_ms_{$name} Average latency in ms.";
            $lines[] = "# TYPE api_latency_avg_ms_{$name} gauge";
            $lines[] = "api_latency_avg_ms_{$name} {$item['avg_ms']}";
        }

        return implode("\n", $lines)."\n";
    }

    public static function metricEndpoints(): array
    {
        return ['products_show', 'jobs_store', 'jobs_bulk', 'jobs_show'];
    }
}
