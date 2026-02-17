<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DemoDataFactory;
use Illuminate\Http\JsonResponse;

class SystemController extends Controller
{
    public function syncOfflineBrief(): JsonResponse
    {
        return response()->json([
            'syncedAt' => now()->toIso8601String(),
            'message' => 'Offline brief synced successfully.',
        ]);
    }

    public function requestExport(): JsonResponse
    {
        return response()->json([
            'message' => 'Farm data export has been scheduled and will be emailed shortly.',
        ]);
    }

    public function supportLinks(): JsonResponse
    {
        return response()->json([
            'links' => DemoDataFactory::supportLinks(),
        ]);
    }
}
