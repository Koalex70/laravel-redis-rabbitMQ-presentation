<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductShowResource;
use App\Services\ProductCacheService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductCacheService $productCacheService,
    ) {
    }

    public function show(int $id): JsonResponse
    {
        $startedAt = microtime(true);
        $result = $this->productCacheService->findById($id);
        $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($result === null) {
            return response()
                ->json([
                    'message' => 'Product not found',
                    'product_id' => $id,
                ], 404)
                ->header('X-Cache', 'MISS')
                ->header('X-Response-Time-Ms', (string) $responseTimeMs);
        }

        return response()
            ->json((new ProductShowResource($result))->resolve())
            ->header('X-Cache', $result['cache'])
            ->header('X-Response-Time-Ms', (string) $responseTimeMs);
    }
}
