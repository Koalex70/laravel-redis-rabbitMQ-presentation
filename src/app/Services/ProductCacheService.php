<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Redis;

class ProductCacheService
{
    public function findById(int $id): ?array
    {
        $cacheKey = $this->cacheKey($id);
        $cachedPayload = Redis::get($cacheKey);

        if (is_string($cachedPayload)) {
            $decodedPayload = json_decode($cachedPayload, true);

            if (is_array($decodedPayload)) {
                return [
                    'product' => $decodedPayload,
                    'cache' => 'HIT',
                    'cache_key' => $cacheKey,
                ];
            }
        }

        $product = Product::query()->find($id);

        if ($product === null) {
            return null;
        }

        $this->simulateMissLatency();

        $payload = $product->toArray();
        $ttlSeconds = max(1, (int) env('CACHE_TTL_SECONDS', 60));
        Redis::setex($cacheKey, $ttlSeconds, json_encode($payload));

        return [
            'product' => $payload,
            'cache' => 'MISS',
            'cache_key' => $cacheKey,
        ];
    }

    private function cacheKey(int $id): string
    {
        return "cache:product:{$id}";
    }

    private function simulateMissLatency(): void
    {
        $delayMs = max(0, (int) env('CACHE_MISS_DELAY_MS', 350));

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }
}
