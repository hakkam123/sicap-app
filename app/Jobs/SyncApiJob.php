<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Services\ConsumeSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncApiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public ?string $userId = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}

    public function handle(ConsumeSyncService $syncService): void
    {
        $dateFrom = $this->dateFrom ?: now()->subDay()->format('Y-m-d');
        $dateTo = $this->dateTo ?: now()->format('Y-m-d');

        $importLog = ImportLog::create([
            'feature' => 'sync_api',
            'filename' => "API BAAN ({$dateFrom} s/d {$dateTo})",
            'status' => 'processing',
            'started_at' => now(),
            'user_id' => $this->userId,
        ]);

        Cache::put('sync_api_status', 'processing', now()->addMinutes(15));

        try {
            $result = $syncService->sync(null, $this->userId, $this->dateFrom, $this->dateTo);

            $syncedCount = $result['synced_count'] ?? 0;
            $skippedCount = $result['skipped_count'] ?? 0;
            $totalFromApi = $result['total'] ?? 0;
            $errors = $result['errors'] ?? [];
            $status = ($result['status'] ?? 'success') === 'error' ? 'failed' : 'success';

            $importLog->update([
                'status' => $status,
                'total_rows' => $totalFromApi,
                'processed_rows' => $syncedCount,
                'success_rows' => $syncedCount,
                'failed_rows' => count($errors),
                'error_message' => !empty($errors) ? json_encode(array_slice($errors, 0, 50), JSON_UNESCAPED_UNICODE) : null,
                'finished_at' => now(),
            ]);

            Cache::put('sync_api_status', 'completed', now()->addMinutes(30));
            Cache::put('sync_api_result', $result, now()->addMinutes(30));

            Log::info('SyncApiJob completed', [
                'status' => $result['status'] ?? 'unknown',
                'synced' => $syncedCount,
                'skipped' => $skippedCount,
            ]);
        } catch (\Throwable $e) {
            $importLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Cache::put('sync_api_status', 'failed', now()->addMinutes(30));
            Cache::put('sync_api_result', [
                'status' => 'error',
                'message' => $e->getMessage(),
            ], now()->addMinutes(30));

            Log::error("SyncApiJob failed: {$e->getMessage()}");

            throw $e;
        }
    }
}
