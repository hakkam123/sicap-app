<?php

namespace App\Jobs;

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
        Cache::put('sync_api_status', 'processing', now()->addMinutes(15));

        try {
            $result = $syncService->sync(null, $this->userId, $this->dateFrom, $this->dateTo);

            Cache::put('sync_api_status', 'completed', now()->addMinutes(30));
            Cache::put('sync_api_result', $result, now()->addMinutes(30));

            Log::info('SyncApiJob completed', [
                'status' => $result['status'] ?? 'unknown',
                'synced' => $result['synced_count'] ?? 0,
                'skipped' => $result['skipped_count'] ?? 0,
            ]);
        } catch (\Throwable $e) {
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
