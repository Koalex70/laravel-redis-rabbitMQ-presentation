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
                Redis::incr('metrics:cache:hits');
                return [
                    'product' => $decodedPayload,
                    'cache' => 'HIT',
                    'cache_key' => $cacheKey,
                ];
            }
        }

        $product = Product::query()->find($id);

        if ($product === null) {
            Redis::incr('metrics:cache:misses');
            return null;
        }

        $this->simulateMissLatency();

        $payload = $product->toArray();
        $ttlSeconds = max(1, (int) env('CACHE_TTL_SECONDS', 60));
        Redis::setex($cacheKey, $ttlSeconds, json_encode($payload));
        Redis::incr('metrics:cache:misses');

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

    public function flushAll(): int
    {
        $keys = Redis::keys('cache:product:*');

        if (!is_array($keys) || $keys === []) {
            return 0;
        }

        return (int) Redis::del($keys);
    }

    private function simulateMissLatency(): void
    {
        $delayMs = max(0, (int) env('CACHE_MISS_DELAY_MS', 350));

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }
}
