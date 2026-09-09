<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ConsumeApiController extends Controller
{
    /**
     * Synchronize consume records from external systems (Batch Sync).
     */
    public function sync(Request $request, \App\Services\ConsumeSyncService $syncService): JsonResponse
    {
        // 1. Validasi struktur payload (Maksimal 500 item per request untuk mencegah memory & database lock contention)
        $validator = Validator::make($request->all(), [
            'consumes' => ['required', 'array', 'min:1', 'max:500'],
            'consumes.*.part_number' => ['required', 'string'],
            'consumes.*.area_code' => ['nullable', 'string'],
            'consumes.*.machine_code' => ['nullable', 'string'],
            'consumes.*.quantity' => ['required', 'integer'],
            'consumes.*.amount' => ['nullable', 'numeric'],
            'consumes.*.consumed_at' => ['required', 'date_format:Y-m-d H:i:s'],
        ], [
            'consumes.required' => 'Payload consumes wajib disertakan.',
            'consumes.min' => 'Payload consumes minimal berisi 1 rekaman data.',
            'consumes.max' => 'Payload consumes maksimal berisi 500 rekaman data per request.',
            'consumes.*.part_number.required' => 'Kolom part_number wajib diisi.',
            'consumes.*.quantity.required' => 'Kolom quantity wajib diisi.',
            'consumes.*.quantity.integer' => 'Kolom quantity harus berupa bilangan bulat.',
            'consumes.*.consumed_at.required' => 'Kolom consumed_at wajib diisi.',
            'consumes.*.consumed_at.date_format' => 'Format consumed_at harus Y-m-d H:i:s (contoh: 2026-09-03 10:00:00).',
        ]);

        if ($validator->fails()) {
            $formattedErrors = [];
            foreach ($validator->errors()->messages() as $key => $messages) {
                if (preg_match('/consumes\.(\d+)\.(.+)/', $key, $matches)) {
                    $formattedErrors[] = [
                        'row' => (int) $matches[1] + 1,
                        'field' => $matches[2],
                        'message' => $messages[0],
                    ];
                } else {
                    $formattedErrors[] = [
                        'row' => null,
                        'field' => $key,
                        'message' => $messages[0],
                    ];
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $formattedErrors,
            ], 422);
        }

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
            // Catat log error database
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