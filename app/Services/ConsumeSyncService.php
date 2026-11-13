<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Support\ConsumeUpserter;
use App\Support\IndonesianFormatParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConsumeSyncService
{
    /**
     * Run full sync from external API (BAAN Adjustment Data) into SICAP database.
     *
     * @param array|null $overrideItems Optional items array (for testing or manual payload)
     * @param string|null $userId ID of the user triggering manual sync (null for automated scheduler)
     * @param string|null $dateFrom Start date filter (Y-m-d), defaults to yesterday
     * @param string|null $dateTo End date filter (Y-m-d), defaults to today
     * @return array
     * @throws \Throwable
     */
    public function sync(?array $overrideItems = null, ?string $userId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $startTime = now();
        Log::info("Sync API Started at {$startTime->toDateTimeString()}");

        try {
            $items = $overrideItems;
            $totalFromApi = 0;
            $skippedCount = 0;

            if ($items === null) {
                $baseUrl = rtrim(config('services.external_api.url', ''), '/');
                $username = config('services.external_api.username', '');
                $password = config('services.external_api.password', '');
                $timeout = (int) config('services.external_api.timeout', 60);

                if (empty($baseUrl)) {
                    $msg = 'EXTERNAL_API_URL belum dikonfigurasi di file .env.';
                    Log::warning("Sync API Warning: {$msg}");

                    return [
                        'status' => 'warning',
                        'message' => $msg,
                        'total' => 0,
                        'synced_count' => 0,
                        'skipped_count' => 0,
                        'start_time' => $startTime->toDateTimeString(),
                        'end_time' => now()->toDateTimeString(),
                        'duration_seconds' => 0,
                    ];
                }

                // Tanggal bisnis mengikuti WIB, bukan timezone aplikasi (UTC)
                $tz = config('services.external_api.timezone', 'Asia/Jakarta');
                $dateFrom = $dateFrom ?: now($tz)->subDay()->format('Y-m-d');
                $dateTo = $dateTo ?: now($tz)->format('Y-m-d');

                if ($dateFrom > $dateTo) {
                    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
                }

                // Antisipasi API yang memperlakukan dateTo sebagai "Y-m-d 00:00:00" (transaksi
                // di hari dateTo tidak ikut). Kirim dateTo + 1 hari, lalu saring lokal ke rentang asli.
                $apiDateTo = Carbon::parse($dateTo)->addDay()->format('Y-m-d');

                $response = Http::timeout($timeout)
                    ->withBasicAuth($username, $password)
                    ->acceptJson()
                    ->get("{$baseUrl}/api/GetAdjustmentData", [
                        'dateFrom' => $dateFrom,
                        'dateTo' => $apiDateTo,
                    ]);

                if (!$response->successful()) {
                    throw new \Exception(
                        "Gagal menghubungi API eksternal (HTTP {$response->status()}): "
                        . Str::limit($response->body(), 300)
                    );
                }

                $payload = $response->json();

                if (($payload['Status'] ?? '') !== 'success') {
                    throw new \Exception(
                        'API mengembalikan error: ' . ($payload['Message'] ?? 'Tidak ada pesan error.')
                    );
                }

                $apiData = array_values(array_filter(
                    $payload['Data'] ?? [],
                    function ($row) use ($dateFrom, $dateTo) {
                        $d = substr((string) ($row['TransactionDate'] ?? ''), 0, 10);

                        return $d === '' || ($d >= $dateFrom && $d <= $dateTo);
                    }
                ));
                $totalFromApi = count($apiData);

                if (empty($apiData)) {
                    Cache::forever('last_api_sync_at', now()->toIso8601String());

                    return [
                        'status' => 'success',
                        'message' => "Tidak ada data baru dari API untuk periode {$dateFrom} s/d {$dateTo}.",
                        'total' => 0,
                        'synced_count' => 0,
                        'skipped_count' => 0,
                        'start_time' => $startTime->toDateTimeString(),
                        'end_time' => now()->toDateTimeString(),
                        'duration_seconds' => now()->diffInSeconds($startTime),
                    ];
                }

                // Transform BAAN API response to internal format
                $items = collect($apiData)->map(fn($row) => [
                    'part_number' => trim($row['Item'] ?? ''),
                    'date' => $row['TransactionDate'] ?? null,
                    'qty' => (int) ($row['Qty'] ?? 0),
                    'amount' => $row['Amount'] ?? 0,
                ])->all();
            }

            if (empty($items)) {
                $endTime = now();
                Cache::forever('last_api_sync_at', $endTime->toIso8601String());

                return [
                    'status' => 'success',
                    'message' => "Semua {$skippedCount} data sudah tersinkronisasi sebelumnya.",
                    'total' => $totalFromApi,
                    'synced_count' => 0,
                    'skipped_count' => $skippedCount,
                    'start_time' => $startTime->toDateTimeString(),
                    'end_time' => $endTime->toDateTimeString(),
                    'duration_seconds' => $endTime->diffInSeconds($startTime),
                ];
            }

            // Sync BAAN: baris valid tetap disimpan walau ada PN yang belum terdaftar di master
            $result = $this->processItems($items, $userId, partial: true);

            $endTime = now();
            $duration = $endTime->diffInSeconds($startTime);
            $syncedCount = $result['inserted'] ?? 0;
            $skippedCount = $result['updated'] ?? 0;
            $hasErrors = !empty($result['errors']);

            Cache::forever('last_api_sync_at', $endTime->toIso8601String());

            $messageParts = [];
            if ($syncedCount > 0) {
                $messageParts[] = "{$syncedCount} data baru disinkronkan";
            }
            if ($skippedCount > 0) {
                $messageParts[] = "{$skippedCount} data sudah ada (diperbarui, tidak dobel)";
            }
            if ($hasErrors) {
                $messageParts[] = count($result['errors']) . ' data gagal validasi';
            }
            if (empty($messageParts)) {
                $messageParts[] = 'Tidak ada data yang diproses';
            }

            $status = 'success';
            if ($hasErrors && $syncedCount + $skippedCount === 0) {
                $status = 'error';
            } elseif ($hasErrors) {
                $status = 'partial';
            }

            Log::info("Sync API Completed at {$endTime->toDateTimeString()} ({$duration}s).", [
                'total_from_api' => $totalFromApi,
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount,
                'errors_count' => count($result['errors'] ?? []),
            ]);

            return [
                'status' => $status,
                'message' => implode(', ', $messageParts) . '.',
                'total' => $totalFromApi ?: count($items),
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount,
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
     * @param bool $partial true = simpan baris valid & laporkan baris gagal; false = semua-atau-tidak-sama-sekali
     * @return array
     */
    public function processItems(array $items, ?string $userId = null, bool $partial = false): array
    {
        if (empty($items)) {
            return ['total' => 0, 'processed' => 0, 'errors' => []];
        }

        $businessErrors = [];
        $recordsToInsert = [];

        // 1. Bulk Pre-fetch master data to prevent N+1 queries
        $pnCodes = collect($items)
            ->map(fn($item) => trim((string)($item['part_number'] ?? $item['pn_baan'] ?? '')))
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

        $partMap = PartNumber::with(['areas', 'machines'])->whereIn('pn_baan', $pnCodes)->get()->keyBy('pn_baan');
        $machineMap = !empty($machineCodes) ? Machine::whereIn('code', $machineCodes)->get()->keyBy('code') : collect();

        // Build comprehensive area lookup mapping (supports code, full name, and acronym)
        $allAreas = Area::all();
        $areaLookup = [];
        foreach ($allAreas as $a) {
            if (!empty($a->code)) {
                $areaLookup[strtoupper(trim($a->code))] = $a;
            }
            if (!empty($a->name)) {
                $areaLookup[strtoupper(trim($a->name))] = $a;
                $areaLookup[strtoupper(Area::abbreviate($a->name))] = $a;
            }
        }

        // 2. Validate items against master data
        foreach ($items as $index => $item) {
            $rowNum = $index + 1;
            $errorsBeforeRow = count($businessErrors);
            $pnCode = trim((string) ($item['part_number'] ?? $item['pn_baan'] ?? ''));
            $rawDate = $item['date'] ?? $item['consumed_at'] ?? null;
            $rawQty = $item['qty'] ?? $item['quantity'] ?? null;
            $rawAmount = $item['amount'] ?? null;

            $rawAreaKey = isset($item['area_code']) && trim((string)$item['area_code']) !== ''
                ? trim((string)$item['area_code'])
                : (isset($item['area']) && trim((string)$item['area']) !== '' ? trim((string)$item['area']) : null);
            $machineCode = isset($item['machine_code']) && trim((string)$item['machine_code']) !== ''
                ? trim((string)$item['machine_code'])
                : null;

            // 1. Validate Part Number
            $part = $partMap->get($pnCode);
            if (!$part) {
                $businessErrors[] = [
                    'row' => $rowNum,
                    'field' => 'part_number',
                    'message' => "Part number '{$pnCode}' tidak ditemukan di master data.",
                ];
            }

            // 2. Validate & Parse Date
            $consumedAt = IndonesianFormatParser::parseDate($rawDate);
            if (!$consumedAt) {
                $businessErrors[] = [
                    'row' => $rowNum,
                    'field' => 'date',
                    'message' => "Format tanggal '{$rawDate}' tidak valid.",
                ];
            }

            // 3. Validate & Parse Quantity
            if ($rawQty === null || trim((string) $rawQty) === '') {
                $businessErrors[] = [
                    'row' => $rowNum,
                    'field' => 'qty',
                    'message' => "Kolom 'qty' wajib diisi.",
                ];
                $quantity = 0;
            } else {
                $quantity = IndonesianFormatParser::parseQty($rawQty);
                if ($quantity === 0) {
                    $businessErrors[] = [
                        'row' => $rowNum,
                        'field' => 'qty',
                        'message' => "Kolom 'qty' tidak boleh bernilai 0.",
                    ];
                }
            }

            // 4. Validate & Parse Amount (Format Indonesia: titik ribuan, koma desimal, boleh negatif)
            if ($rawAmount === null || trim((string) $rawAmount) === '') {
                $businessErrors[] = [
                    'row' => $rowNum,
                    'field' => 'amount',
                    'message' => "Kolom 'amount' wajib diisi.",
                ];
                $amount = null;
            } else {
                $amount = IndonesianFormatParser::parseAmount($rawAmount);
                if ($amount === null) {
                    $businessErrors[] = [
                        'row' => $rowNum,
                        'field' => 'amount',
                        'message' => "Format nominal amount '{$rawAmount}' tidak valid.",
                    ];
                }
            }

            // 5. Validate Area (opsional jika disediakan)
            $area = null;
            if ($rawAreaKey !== null) {
                $area = $areaLookup[strtoupper($rawAreaKey)] ?? null;
                if (!$area) {
                    $businessErrors[] = [
                        'row' => $rowNum,
                        'field' => 'area_code',
                        'message' => "Area '{$rawAreaKey}' tidak ditemukan.",
                    ];
                }
            }

            // 6. Validate Machine (opsional jika disediakan)
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

            // 7. Validate Machine and Area relationship
            if ($machine && $area && $machine->area_id !== $area->id) {
                $businessErrors[] = [
                    'row' => $rowNum,
                    'field' => 'machine_code',
                    'message' => "Machine '{$machineCode}' tidak berada di Area '{$rawAreaKey}'.",
                ];
            }

            // Lewati hanya baris ini jika baris ini punya error
            if (count($businessErrors) > $errorsBeforeRow) {
                continue;
            }

            $finalAreaId = $area?->id ?? $machine?->area_id ?? $part->areas->first()?->id ?? null;
            $finalMachineId = $machine?->id ?? $part->machines->first()?->id ?? null;

            $recordsToInsert[] = [
                'part_number_id' => $part->id,
                'area_id' => $finalAreaId,
                'machine_id' => $finalMachineId,
                'quantity' => $quantity,
                'amount' => $amount,
                'consumed_at' => $consumedAt,
                'source' => 'api',
                'created_by' => $userId,
            ];
        }

        if (!empty($businessErrors) && (!$partial || empty($recordsToInsert))) {
            return [
                'total' => count($items),
                'processed' => 0,
                'inserted' => 0,
                'updated' => 0,
                'errors' => $businessErrors,
            ];
        }

        // 3. Simpan tanpa dobel lintas sumber (import Excel / API / manual); data terbaru menang
        $saved = ConsumeUpserter::upsertLatest($recordsToInsert);

        return [
            'total' => count($items),
            'processed' => $saved['inserted'] + $saved['updated'],
            'inserted' => $saved['inserted'],
            'updated' => $saved['updated'],
            'errors' => $businessErrors,
        ];
    }
}
