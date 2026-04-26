<?php

namespace App\Http\Controllers;

use App\Http\Resources\DependencyHealthResource;
use App\Http\Resources\HealthStatusResource;
use App\Models\HealthReport;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function app(): JsonResponse
    {
        return (new HealthStatusResource(HealthReport::app()))
            ->response()
            ->setStatusCode(200);
    }

    public function dependencies(): JsonResponse
    {
        $report = HealthReport::dependencies();
        $statusCode = $report['status'] === 'ok' ? 200 : 503;

        return (new DependencyHealthResource($report))
            ->response()
            ->setStatusCode($statusCode);
    }
}
