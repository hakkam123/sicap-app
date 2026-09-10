<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsumeApiRequest;
use App\Services\ConsumeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ConsumeApiController extends Controller
{
    /**
     * Synchronize consume records from external systems (Batch Sync).
     */
    public function sync(ConsumeApiRequest $request, ConsumeSyncService $syncService): JsonResponse
    {
        $items = $request->input('consumes');
        $userId = Auth::id();

        try {
            $result = $syncService->processItems($items, $userId);

            if (!empty($result['errors'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $result['errors'],
                ], 422);
            }

            $totalCount = $result['processed'] ?? 0;

            return response()->json([
                'status' => 'success',
                'message' => "{$totalCount} records synced",
                'data' => [
                    'total' => count($items),
                    'processed' => $totalCount,
                    'failed' => 0,
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::channel('security')->error('API Sync Database Error', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_id' => $userId,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan data ke database.',
                'errors' => [],
            ], 500);
        }
    }
}