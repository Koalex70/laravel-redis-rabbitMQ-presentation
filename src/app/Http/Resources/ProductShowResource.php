<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => (new ProductResource($this['product']))->toArray($request),
            'meta' => [
                'cache' => $this['cache'],
                'cache_key' => $this['cache_key'],
            ],
        ];
    }
}
