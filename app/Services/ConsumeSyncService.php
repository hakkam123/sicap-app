<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConsumeSyncService
{
    /**
     * Run full sync from external API into SICAP database.
     *
     * @param array|null $overrideItems Optional items array (for testing or manual payload)
     * @param string|null $userId ID of the user triggering manual sync (null for automated scheduler)
     * @return array
     * @throws \Throwable
     */
    public function sync(?array $overrideItems = null, ?string $userId = null): array
    {
        $startTime = now();
        Log::info("Sync API Started at {$startTime->toDateTimeString()}");

        try {
            $items = $overrideItems;

            if ($items === null) {
                $url = config('services.external_api.url', env('EXTERNAL_API_URL'));
                $token = config('services.external_api.token', env('EXTERNAL_API_TOKEN'));
                $timeout = (int) config('services.external_api.timeout', env('EXTERNAL_API_TIMEOUT', 60));

                if (empty($url)) {
                    $msg = 'EXTERNAL_API_URL belum dikonfigurasi di file .env.';
                    Log::warning("Sync API Warning: {$msg}");

                    return [
                        'status' => 'warning',
                        'message' => $msg,
                        'total' => 0,
                        'synced_count' => 0,
                        'start_time' => $startTime->toDateTimeString(),
                        'end_time' => now()->toDateTimeString(),
                        'duration_seconds' => 0,
                    ];
                }

                $client = Http::timeout($timeout);
                if (!empty($token)) {
                    $client = $client->withToken($token);
                }

                $response = $client->acceptJson()->get($url);

                if (!$response->successful()) {
                    throw new \Exception("Gagal menghubungi API eksternal (HTTP {$response->status()}): " . Str::limit($response->body(), 300));
                }

                $payload = $response->json();
                $items = $payload['consumes'] ?? $payload['data'] ?? $payload;

                if (!is_array($items)) {
                    throw new \Exception("Format respons API tidak valid: payload bukan array data.");
                }
            }

            // Process items into database
            $result = $this->processItems($items, $userId);

            $endTime = now();
            $duration = $endTime->diffInSeconds($startTime);
            $syncedCount = $result['processed'] ?? 0;

            Log::info("Sync API Completed at {$endTime->toDateTimeString()} ({$duration}s). Synced {$syncedCount} records.", [
                'total_received' => count($items),
                'synced_count' => $syncedCount,
                'errors_count' => count($result['errors'] ?? []),
            ]);

            return [
                'status' => 'success',
                'message' => "{$syncedCount} records synced",
                'total' => count($items),
                'synced_count' => $syncedCount,
                'errors' => $result['errors'] ?? [],
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'duration_seconds' => $duration,
            ];

        } catch (\Throwable $e) {
            $endTime = now();
            Log::error("Sync API Failed at {$endTime->toDateTimeString()}: " . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate and bulk insert consume records into database.
     *
     * @param array $items
     * @param string|null $userId
     * @return array
     */
    public function processItems(array $items, ?string $userId = null): array
    {
        if (empty($items)) {
            return ['total' => 0, 'processed' => 0, 'errors' => []];
        }

        $businessErrors = [];
        $recordsToInsert = [];
        $now = now();

        // 1. Bulk Pre-fetch master data to prevent N+1 queries
        $pnCodes = collect($items)
            ->map(fn($item) => trim((string)($item['part_number'] ?? $item['pn_baan'] ?? '')))
            ->unique()
            ->filter()
            ->values()
            ->all();

        $areaCodes = collect($items)
            ->map(fn($item) => isset($item['area_code']) ? trim((string)$item['area_code']) : '')
            ->unique()
            ->filter()
            ->values()
            ->all();

        $machineCodes = collect($items)
            ->map(fn($item) => isset($item['machine_code']) ? trim((string)$item['machine_code']) : '')
            ->unique()
            ->filter()
            ->values()
            ->all();

        $partMap = PartNumber::whereIn('pn_baan', $pnCodes)->get()->keyBy('pn_baan');
        $areaMap = !empty($areaCodes) ? Area::whereIn('code', $areaCodes)->get()->keyBy('code') : collect();
        $machineMap = !empty($machineCodes) ? Machine::whereIn('code', $machineCodes)->get()->keyBy('code') : collect();

        // 2. Validate items against master data
        foreach ($items as $index => $item) {
            $rowNum = $index + 1;
            $pnCode = trim((string) ($item['part_number'] ?? $item['pn_baan'] ?? ''));
            $areaCode = isset($item['area_code']) && trim((string)$item['area_code']) !== ''
                ? trim((string)$item['area_code'])
                : null;
            $machineCode = isset($item['machine_code']) && trim((string)$item['machine_code']) !== ''
                ? trim((string)$item['machine_code'])
                : null;
            $quantity = (int) ($item['quantity'] ?? $item['qty'] ?? 0);
            $consumedAt = $item['consumed_at'] ?? $item['date'] ?? $now->toDateTimeString();

            // Validate Part Number
            $part = $partMap->get($pnCode);
            if (!$part) {
                $businessErrors[] = [
                    'row' => $rowNum,
                    'field' => 'part_number',
                    'message' => "Part number '{$pnCode}' tidak ditemukan di master data.",
                ];
            }

            // Validate Area (if provided)
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

            // Validate Machine (if provided)
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

            // Validate Machine and Area relationship
            if ($machine && $area && $machine->area_id !== $area->id) {
                $businessErrors[] = [
                    'row' => $rowNum,
                    'field' => 'machine_code',
                    'message' => "Machine '{$machineCode}' tidak berada di Area '{$areaCode}'.",
                ];
            }

            if (!empty($businessErrors)) {
                continue;
            }

            // Calculate amount if omitted
            $amount = isset($item['amount']) && $item['amount'] !== null && $item['amount'] !== ''
                ? (float) $item['amount']
                : null;

            if ($amount === null && $part && $part->price_per_unit !== null) {
                $amount = (float) $part->price_per_unit * abs($quantity);
            }

            $recordsToInsert[] = [
                'id' => (string) Str::ulid(),
                'part_number_id' => $part->id,
                'area_id' => $area?->id ?? $machine?->area_id ?? null,
                'machine_id' => $machine?->id ?? null,
                'quantity' => $quantity,
                'amount' => $amount,
                'consumed_at' => $consumedAt,
                'source' => 'api',
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($businessErrors)) {
            return [
                'total' => count($items),
                'processed' => 0,
                'errors' => $businessErrors,
            ];
        }

        // 3. Database bulk transaction in chunks
        DB::transaction(function () use ($recordsToInsert) {
            foreach (array_chunk($recordsToInsert, 100) as $chunk) {
                Consume::insert($chunk);
            }
        });

        return [
            'total' => count($items),
            'processed' => count($recordsToInsert),
            'errors' => [],
        ];
    }
}

