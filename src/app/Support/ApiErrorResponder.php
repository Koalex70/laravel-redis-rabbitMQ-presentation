<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiErrorResponder
{
    public static function respond(string $code, string $message, int $statusCode, array $details = []): JsonResponse
    {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        return response()->json($payload, $statusCode);
    }
}
