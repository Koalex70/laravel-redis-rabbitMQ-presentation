<?php

namespace App\Http\Controllers;

use App\Http\Requests\DemoEnqueueRequest;
use App\Http\Resources\ApiEnvelopeResource;
use App\Models\DemoScenario;
use App\Services\Metrics\ApiLatencyMetricsService;
use App\Services\ProductCacheService;
use App\Services\Queue\ReportJobQueueService;
use Illuminate\Http\JsonResponse;

class DemoController extends Controller
{
    public function __construct(
        private readonly ProductCacheService $productCacheService,
        private readonly ReportJobQueueService $queueService,
        private readonly ApiLatencyMetricsService $latencyMetrics,
    ) {
    }

    public function flushCache(): JsonResponse
    {
        return (new ApiEnvelopeResource([
            'data' => DemoScenario::flushCache($this->productCacheService),
        ]))
            ->response()
            ->setStatusCode(200);
    }

    public function resetMetrics(): JsonResponse
    {
        return (new ApiEnvelopeResource([
            'data' => DemoScenario::resetMetrics($this->queueService, $this->latencyMetrics),
        ]))
            ->response()
            ->setStatusCode(200);
    }

    public function enqueueDemoJobs(DemoEnqueueRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $count = (int) ($validated['count'] ?? 50);
        $forceFail = (bool) ($validated['force_fail'] ?? false);
        return (new ApiEnvelopeResource([
            'data' => DemoScenario::enqueueDemoJobs($this->queueService, $count, $forceFail),
        ]))
            ->response()
            ->setStatusCode(202);
    }
}
