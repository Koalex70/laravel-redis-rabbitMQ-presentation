<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBulkReportJobsRequest;
use App\Http\Requests\StoreReportJobRequest;
use App\Http\Resources\ReportJobResource;
use App\Models\ReportJob;
use App\Services\Queue\ReportJobQueueService;
use App\Support\ApiErrorResponder;
use Illuminate\Http\JsonResponse;

class JobController extends Controller
{
    public function __construct(
        private readonly ReportJobQueueService $queueService,
    ) {
    }

    public function store(StoreReportJobRequest $request): JsonResponse
    {
        $job = $this->queueService->enqueue($request->validated());

        return response()->json([
            'data' => (new ReportJobResource($job))->resolve(),
            'meta' => [
                'queue_depth' => $this->queueService->queueDepth(),
            ],
        ], 202);
    }

    public function bulk(StoreBulkReportJobsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $count = (int) $validated['count'];
        $payload = $validated['payload'];
        $jobs = [];

        for ($i = 0; $i < $count; $i++) {
            $jobs[] = $this->queueService->enqueue($payload);
        }

        return response()->json([
            'data' => array_map(
                static fn (ReportJob $job): array => (new ReportJobResource($job))->resolve(),
                $jobs
            ),
            'meta' => [
                'count' => $count,
                'queue_depth' => $this->queueService->queueDepth(),
            ],
        ], 202);
    }

    public function show(string $id): JsonResponse
    {
        $job = ReportJob::query()->find($id);

        if ($job === null) {
            return ApiErrorResponder::respond(
                'job_not_found',
                'Job not found.',
                404,
                ['job_id' => $id],
            );
        }

        return response()->json([
            'data' => (new ReportJobResource($job))->resolve(),
        ]);
    }
}
