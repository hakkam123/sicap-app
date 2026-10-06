<?php

namespace App\Console\Commands;

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
        $dateFrom = $this->option('date-from');
        $dateTo = $this->option('date-to');

        $this->info('====================================================');
        $this->info(' SICAP API Synchronization');
        $this->info(" Started at: {$startTime->toDateTimeString()}");
        $this->info(' Date range: ' . ($dateFrom ?: 'yesterday') . ' s/d ' . ($dateTo ?: 'today'));
        $this->info('====================================================');

        try {
            $result = $syncService->sync(null, null, $dateFrom, $dateTo);

            if (($result['status'] ?? '') === 'warning') {
                $this->warn($result['message'] ?? 'Sinkronisasi tidak dijalankan.');
                return self::SUCCESS;
            }

            $syncedCount = $result['synced_count'] ?? 0;
            $skippedCount = $result['skipped_count'] ?? 0;
            $duration = $result['duration_seconds'] ?? 0;
            $endTime = $result['end_time'] ?? now()->toDateTimeString();

            $this->info("Synchronization completed at: {$endTime}");
            $this->info("Synced: {$syncedCount} records");
            if ($skippedCount > 0) {
                $this->info("Skipped (duplicate): {$skippedCount} records");
            }
            if (!empty($result['errors'])) {
                $this->warn("Validation errors: " . count($result['errors']) . " records");
            }
            $this->info("Execution time: {$duration} seconds");
            $this->info('====================================================');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Synchronization failed: " . $e->getMessage());

            Log::error("Artisan Command [copa:sync-api] Exception: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
