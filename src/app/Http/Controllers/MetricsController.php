<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiEnvelopeResource;
use App\Models\MetricsSnapshot;
use App\Services\Metrics\ApiLatencyMetricsService;
use App\Services\Queue\ReportJobQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __construct(
        private readonly ReportJobQueueService $queueService,
        private readonly ApiLatencyMetricsService $latencyMetrics,
    ) {
    }

    public function cache(): JsonResponse
    {
        return (new ApiEnvelopeResource([
            'data' => MetricsSnapshot::cache(),
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ]))
            ->response()
            ->setStatusCode(200);
    }

    public function queue(): JsonResponse
    {
        return (new ApiEnvelopeResource([
            'data' => MetricsSnapshot::queue($this->queueService),
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ]))
            ->response()
            ->setStatusCode(200);
    }

    public function overview(): JsonResponse
    {
        return (new ApiEnvelopeResource([
            'data' => MetricsSnapshot::overview($this->queueService, $this->latencyMetrics),
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ]))
            ->response()
            ->setStatusCode(200);
    }

    public function prometheus(): Response
    {
        return response(MetricsSnapshot::prometheus($this->queueService, $this->latencyMetrics), 200, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }
}
