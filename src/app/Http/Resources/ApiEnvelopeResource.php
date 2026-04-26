<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiEnvelopeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $response = [
            'data' => $this['data'] ?? null,
        ];

        if (isset($this['meta']) && is_array($this['meta'])) {
            $response['meta'] = $this['meta'];
        }

        return $response;
    }
}
