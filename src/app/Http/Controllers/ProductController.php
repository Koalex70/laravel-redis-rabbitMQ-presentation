<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductShowResource;
use App\Services\Metrics\ApiLatencyMetricsService;
use App\Services\ProductCacheService;
use App\Support\ApiErrorResponder;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductCacheService $productCacheService,
        private readonly ApiLatencyMetricsService $latencyMetrics,
    ) {
    }

    public function show(int $id): JsonResponse
    {
        $startedAt = microtime(true);
        $result = $this->productCacheService->findById($id);
        $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);
        $this->latencyMetrics->record('products_show', $responseTimeMs);

        if ($result === null) {
            return ApiErrorResponder::respond(
                'product_not_found',
                'Product not found.',
                404,
                ['product_id' => $id],
            )
                ->header('X-Cache', 'MISS')
                ->header('X-Response-Time-Ms', (string) $responseTimeMs);
        }

        return response()
            ->json((new ProductShowResource($result))->resolve())
            ->header('X-Cache', $result['cache'])
            ->header('X-Response-Time-Ms', (string) $responseTimeMs);
    }
}
