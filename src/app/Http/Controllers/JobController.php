<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBulkReportJobsRequest;
use App\Http\Requests\StoreReportJobRequest;
use App\Http\Resources\ApiEnvelopeResource;
use App\Http\Resources\ReportJobResource;
use App\Models\ReportJobFlow;
use App\Services\Metrics\ApiLatencyMetricsService;
use App\Services\Queue\ReportJobQueueService;
use App\Support\ApiErrorResponder;
use Illuminate\Http\JsonResponse;

class JobController extends Controller
{
    public function __construct(
        private readonly ReportJobQueueService $queueService,
        private readonly ApiLatencyMetricsService $latencyMetrics,
    ) {
    }

    public function store(StoreReportJobRequest $request): JsonResponse
    {
        $result = ReportJobFlow::enqueueOne(
            $this->queueService,
            $this->latencyMetrics,
            $request->validated(),
        );

        return (new ApiEnvelopeResource([
            'data' => (new ReportJobResource($result['job']))->resolve(),
            'meta' => [
                'queue_depth' => $result['queue_depth'],
            ],
        ]))
            ->response()
            ->setStatusCode(202);
    }

    public function bulk(StoreBulkReportJobsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = ReportJobFlow::enqueueBulk(
            $this->queueService,
            $this->latencyMetrics,
            (int) $validated['count'],
            $validated['payload'],
        );

        return (new ApiEnvelopeResource([
            'data' => array_map(
                static fn (mixed $job): array => (new ReportJobResource($job))->resolve(),
                $result['jobs']
            ),
            'meta' => [
                'count' => $result['count'],
                'queue_depth' => $result['queue_depth'],
            ],
        ]))
            ->response()
            ->setStatusCode(202);
    }

    public function show(string $id): JsonResponse
    {
        $job = ReportJobFlow::findById($this->latencyMetrics, $id);

        if ($job === null) {
            return ApiErrorResponder::respond(
                'job_not_found',
                'Job not found.',
                404,
                ['job_id' => $id],
            );
        }

        return (new ApiEnvelopeResource([
            'data' => (new ReportJobResource($job))->resolve(),
        ]))
            ->response()
            ->setStatusCode(200);
    }
}
