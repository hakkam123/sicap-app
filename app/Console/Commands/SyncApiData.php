<?php

namespace App\Console\Commands;

use App\Services\ConsumeSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncApiData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sicap:sync-api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize consume data from external API into SICAP database';

    /**
     * Execute the console command.
     */
    public function handle(ConsumeSyncService $syncService): int
    {
        $startTime = now();

        $this->info('====================================================');
        $this->info(" SICAP API Synchronization");
        $this->info(" Started at: {$startTime->toDateTimeString()}");
        $this->info('====================================================');

        try {
            $result = $syncService->sync();

            if (($result['status'] ?? '') === 'warning') {
                $this->warn("⚠️  " . ($result['message'] ?? 'Sinkronisasi tidak dijalankan.'));
                return self::SUCCESS;
            }

            $syncedCount = $result['synced_count'] ?? 0;
            $duration = $result['duration_seconds'] ?? 0;
            $endTime = $result['end_time'] ?? now()->toDateTimeString();

            $this->info("✓ Synchronization completed successfully at: {$endTime}");
            $this->info("✓ Synced records: {$syncedCount} items");
            $this->info("✓ Execution time: {$duration} seconds");
            $this->info('====================================================');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("❌ Synchronization failed: " . $e->getMessage());

            Log::error("Artisan Command [sicap:sync-api] Exception: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}

