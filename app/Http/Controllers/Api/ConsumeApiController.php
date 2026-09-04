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
use Illuminate\Support\Facades\Validator;

class ConsumeApiController extends Controller
{
    /**
     * Synchronize consume records from external systems.
     */
    public function sync(Request $request): JsonResponse
    {
        // 1. Initial payload validation
        $validator = Validator::make($request->all(), [
            'consumes' => ['required', 'array', 'min:1'],
            'consumes.*.part_number' => ['required', 'string'],
            'consumes.*.area_code' => ['nullable', 'string'],
            'consumes.*.machine_code' => ['nullable', 'string'],
            'consumes.*.quantity' => ['required', 'integer'],
            'consumes.*.amount' => ['nullable', 'numeric'],
            'consumes.*.consumed_at' => ['required', 'date_format:Y-m-d H:i:s'],
        ], [
            'consumes.required' => 'Payload consumes wajib disertakan.',
            'consumes.min' => 'Payload consumes minimal berisi 1 rekaman data.',
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
        $processedRecords = [];

        try {
            DB::transaction(function () use ($items, &$businessErrors, &$processedRecords) {
                foreach ($items as $index => $item) {
                    $rowNum = $index + 1;
                    $pnCode = trim((string) $item['part_number']);
                    $areaCode = isset($item['area_code']) && trim((string)$item['area_code']) !== ''
                        ? trim((string)$item['area_code'])
                        : null;
                    $machineCode = isset($item['machine_code']) && trim((string)$item['machine_code']) !== ''
                        ? trim((string)$item['machine_code'])
                        : null;

                    // 1. Validate PartNumber
                    $part = PartNumber::where('pn_baan', $pnCode)->first();
                    if (!$part) {
                        $businessErrors[] = [
                            'row' => $rowNum,
                            'field' => 'part_number',
                            'message' => "Part number '{$pnCode}' tidak ditemukan di master data.",
                        ];
                    }

                    // 2. Validate Area (if provided)
                    $area = null;
                    if ($areaCode !== null) {
                        $area = Area::where('code', $areaCode)->first();
                        if (!$area) {
                            $businessErrors[] = [
                                'row' => $rowNum,
                                'field' => 'area_code',
                                'message' => "Area dengan kode '{$areaCode}' tidak ditemukan.",
                            ];
                        }
                    }

                    // 3. Validate Machine (if provided)
                    $machine = null;
                    if ($machineCode !== null) {
                        $machine = Machine::where('code', $machineCode)->first();
                        if (!$machine) {
                            $businessErrors[] = [
                                'row' => $rowNum,
                                'field' => 'machine_code',
                                'message' => "Machine dengan kode '{$machineCode}' tidak ditemukan.",
                            ];
                        }
                    }

                    // 4. Validate Machine belongs to Area (if both provided)
                    if ($machine && $area && $machine->area_id !== $area->id) {
                        $businessErrors[] = [
                            'row' => $rowNum,
                            'field' => 'machine_code',
                            'message' => "Machine '{$machineCode}' tidak berada di Area '{$areaCode}'.",
                        ];
                    }

                    // If errors exist so far, stop processing this row
                    if (!empty($businessErrors)) {
                        continue;
                    }

                    // Calculate amount if omitted
                    $amount = isset($item['amount']) && $item['amount'] !== null && $item['amount'] !== ''
                        ? (float) $item['amount']
                        : null;

                    if ($amount === null && $part && $part->price_per_unit !== null) {
                        $amount = (float) $part->price_per_unit * abs((int) $item['quantity']);
                    }

                    // Insert consume
                    $consume = Consume::create([
                        'part_number_id' => $part->id,
                        'area_id' => $area?->id ?? $machine?->area_id ?? null,
                        'machine_id' => $machine?->id ?? null,
                        'quantity' => (int) $item['quantity'],
                        'amount' => $amount,
                        'consumed_at' => $item['consumed_at'],
                        'source' => 'api',
                        'created_by' => Auth::id(),
                    ]);

                    $processedRecords[] = $consume;
                }

                if (!empty($businessErrors)) {
                    throw new \Exception('Validation failed');
                }
            });

            $totalCount = count($processedRecords);

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
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $businessErrors,
            ], 422);
        }
    }
}

