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
    public function sync(Request $request): JsonResponse
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
        $businessErrors = [];
        $recordsToInsert = [];
        $userId = Auth::id();
        $now = now();

        // 2. Optimasi Query: Pre-fetch master data (Bulk Select) untuk mencegah N+1 Query
        $pnCodes = collect($items)->pluck('part_number')->map(fn($v) => trim((string)$v))->unique()->filter()->values()->all();
        $areaCodes = collect($items)->pluck('area_code')->map(fn($v) => trim((string)$v))->unique()->filter()->values()->all();
        $machineCodes = collect($items)->pluck('machine_code')->map(fn($v) => trim((string)$v))->unique()->filter()->values()->all();

        // Map data in-memory menggunakan keyBy untuk pencarian O(1)
        $partMap = PartNumber::whereIn('pn_baan', $pnCodes)->get()->keyBy('pn_baan');
        $areaMap = !empty($areaCodes) ? Area::whereIn('code', $areaCodes)->get()->keyBy('code') : collect();
        $machineMap = !empty($machineCodes) ? Machine::whereIn('code', $machineCodes)->get()->keyBy('code') : collect();

        // 3. Validasi aturan bisnis dan susun data untuk bulk insert
        foreach ($items as $index => $item) {
            $rowNum = $index + 1;
            $pnCode = trim((string) $item['part_number']);
            $areaCode = isset($item['area_code']) && trim((string)$item['area_code']) !== ''
                ? trim((string)$item['area_code'])
                : null;
            $machineCode = isset($item['machine_code']) && trim((string)$item['machine_code']) !== ''
                ? trim((string)$item['machine_code'])
                : null;

            // Validasi Part Number
            $part = $partMap->get($pnCode);
            if (!$part) {
                $businessErrors[] = [
                    'row' => $rowNum,
                    'field' => 'part_number',
                    'message' => "Part number '{$pnCode}' tidak ditemukan di master data.",
                ];
            }

            // Validasi Area (jika diberikan)
            $area = null;
            if ($areaCode !== null) {
                $area = $areaMap->get($areaCode);
                if (!$area) {
                    $businessErrors[] = [
                        'row' => $rowNum,
                        'field' => 'area_code',
                        'message' => "Area dengan kode '{$areaCode}' tidak ditemukan.",
                    ];
                }
            }

            // Validasi Machine (jika diberikan)
            $machine = null;
            if ($machineCode !== null) {
                $machine = $machineMap->get($machineCode);
                if (!$machine) {
                    $businessErrors[] = [
                        'row' => $rowNum,
                        'field' => 'machine_code',
                        'message' => "Machine dengan kode '{$machineCode}' tidak ditemukan.",
                    ];
                }
            }

            // Validasi kesesuaian relasi Machine dengan Area
            if ($machine && $area && $machine->area_id !== $area->id) {
                $businessErrors[] = [
                    'row' => $rowNum,
                    'field' => 'machine_code',
                    'message' => "Machine '{$machineCode}' tidak berada di Area '{$areaCode}'.",
                ];
            }

            // Jika ditemukan error pada baris ini, lanjutkan iterasi pengecekan baris lainnya
            if (!empty($businessErrors)) {
                continue;
            }

            // Kalkulasi amount jika null
            $amount = isset($item['amount']) && $item['amount'] !== null && $item['amount'] !== ''
                ? (float) $item['amount']
                : null;

            if ($amount === null && $part && $part->price_per_unit !== null) {
                $amount = (float) $part->price_per_unit * abs((int) $item['quantity']);
            }

            // Siapkan row data (Sertakan ULID dan Timestamps karena insert query builder tidak memicu Eloquent events)
            $recordsToInsert[] = [
                'id' => (string) Str::ulid(),
                'part_number_id' => $part->id,
                'area_id' => $area?->id ?? $machine?->area_id ?? null,
                'machine_id' => $machine?->id ?? null,
                'quantity' => (int) $item['quantity'],
                'amount' => $amount,
                'consumed_at' => $item['consumed_at'],
                'source' => 'api',
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Jika ada kegagalan validasi master data, batalkan penyimpanan
        if (!empty($businessErrors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $businessErrors,
            ], 422);
        }

        // 4. Eksekusi Database Transaction & Bulk Insert per 100 record (mengurangi overhead & lock waktu di SQL Server)
        try {
            DB::transaction(function () use ($recordsToInsert) {
                foreach (array_chunk($recordsToInsert, 100) as $chunk) {
                    Consume::insert($chunk);
                }
            });

            $totalCount = count($recordsToInsert);

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