<?php

namespace App\Console\Commands;

use App\Models\ImportLog;
use App\Services\ConsumeSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncApiData extends Command
{
    protected $signature = 'copa:sync-api
        {--date-from= : Start date filter (Y-m-d), defaults to yesterday}
        {--date-to= : End date filter (Y-m-d), defaults to today}';

    protected $description = 'Synchronize consume data from external BAAN API into SICAP database';

    public function handle(ConsumeSyncService $syncService): int
    {
        $startTime = now();
        $dateFrom = $this->option('date-from') ?: now()->subDay()->format('Y-m-d');
        $dateTo = $this->option('date-to') ?: now()->format('Y-m-d');

        $this->info('====================================================');
        $this->info(' SICAP API Synchronization');
        $this->info(" Started at: {$startTime->toDateTimeString()}");
        $this->info(" Date range: {$dateFrom} s/d {$dateTo}");
        $this->info('====================================================');

        $importLog = ImportLog::create([
            'feature' => 'sync_api',
            'filename' => "Scheduler ({$dateFrom} s/d {$dateTo})",
            'status' => 'processing',
            'started_at' => $startTime,
        ]);

        try {
            $result = $syncService->sync(null, null, $dateFrom, $dateTo);

            if (($result['status'] ?? '') === 'warning') {
                $this->warn($result['message'] ?? 'Sinkronisasi tidak dijalankan.');
                $importLog->update([
                    'status' => 'success',
                    'error_message' => $result['message'] ?? null,
                    'finished_at' => now(),
                ]);
                return self::SUCCESS;
            }

            $syncedCount = $result['synced_count'] ?? 0;
            $skippedCount = $result['skipped_count'] ?? 0;
            $errors = $result['errors'] ?? [];
            $status = ($result['status'] ?? 'success') === 'error' ? 'failed' : 'success';

            $importLog->update([
                'status' => $status,
                'total_rows' => $result['total'] ?? 0,
                'processed_rows' => $syncedCount,
                'success_rows' => $syncedCount,
                'failed_rows' => count($errors),
                'error_message' => !empty($errors) ? json_encode(array_slice($errors, 0, 50), JSON_UNESCAPED_UNICODE) : null,
                'finished_at' => now(),
            ]);

            $this->info("Synced: {$syncedCount} records");
            if ($skippedCount > 0) {
                $this->info("Skipped (duplicate): {$skippedCount} records");
            }
            if (!empty($errors)) {
                $this->warn("Validation errors: " . count($errors) . " records");
            }
            $this->info('====================================================');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $importLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            $this->error("Synchronization failed: " . $e->getMessage());

            Log::error("Artisan Command [copa:sync-api] Exception: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return self::FAILURE;
        }
    }
}
