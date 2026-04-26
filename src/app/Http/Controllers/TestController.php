<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiEnvelopeResource;
use App\Http\Resources\BenchmarkRunResource;
use App\Models\BenchmarkRun;
use App\Services\Queue\BenchmarkRunQueueService;
use App\Support\ApiErrorResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function __construct(
        private readonly BenchmarkRunQueueService $benchmarkQueue,
    ) {
    }

    public function runCache(): JsonResponse
    {
        $run = $this->benchmarkQueue->enqueue('cache');

        return (new ApiEnvelopeResource([
            'data' => (new BenchmarkRunResource($run))->resolve(),
        ]))->response()->setStatusCode(202);
    }

    public function runJobs(): JsonResponse
    {
        $run = $this->benchmarkQueue->enqueue('jobs');

        return (new ApiEnvelopeResource([
            'data' => (new BenchmarkRunResource($run))->resolve(),
        ]))->response()->setStatusCode(202);
    }

    public function history(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->query('limit', 10)));
        $runs = $this->benchmarkQueue->latest($limit);

        return (new ApiEnvelopeResource([
            'data' => $runs->map(static fn (BenchmarkRun $run): array => (new BenchmarkRunResource($run))->resolve())->all(),
        ]))->response()->setStatusCode(200);
    }

    public function show(string $id): JsonResponse
    {
        $run = BenchmarkRun::query()->find($id);
        if ($run === null) {
            return ApiErrorResponder::respond('benchmark_not_found', 'Benchmark run not found.', 404, ['id' => $id]);
        }

        return (new ApiEnvelopeResource([
            'data' => (new BenchmarkRunResource($run))->resolve(),
        ]))->response()->setStatusCode(200);
    }
}
