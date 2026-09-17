<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| 1. API Synchronization Schedules (Eksisting & Dinamis)
|--------------------------------------------------------------------------
| Sinkronisasi konsumsi part dari external API.
*/
if (Schema::hasTable('sync_schedules')) {
    try {
        $schedules = \App\Models\SyncSchedule::where('is_active', true)->get();
        foreach ($schedules as $schedule) {
            if (!empty($schedule->time)) {
                Schedule::command('copa:sync-api')
                    ->dailyAt($schedule->time)
                    ->timezone('Asia/Jakarta')
                    ->withoutOverlapping()
                    ->onOneServer();
            }
        }
    } catch (\Throwable $e) {
        // Fallback to default schedules if query fails
        Schedule::command('copa:sync-api')->dailyAt('05:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
        Schedule::command('copa:sync-api')->dailyAt('11:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
        Schedule::command('copa:sync-api')->dailyAt('17:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
    }
}

/*
|--------------------------------------------------------------------------
| 2. Database Bloat Prevention & Pruning Schedules (P1)
|--------------------------------------------------------------------------
| Mencegah pembengkakan queue database, batch jobs, token, dan error logs.
*/

// Hapus failed queue jobs yang sudah lebih dari 7 hari (168 jam)
Schedule::command('queue:prune-failed --hours=168')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();

// Hapus metadata job batches yang sudah selesai/gagal > 48 jam
Schedule::command('queue:prune-batches --hours=48')
    ->dailyAt('02:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();

// Hapus token API Sanctum yang sudah expired / tidak aktif > 30 hari (720 jam)
Schedule::command('sanctum:prune-expired --hours=720')
    ->weeklyOn(0, '03:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();

// Cleanup log error sistem & log import lama secara chunked
Schedule::command('copa:cleanup-logs')
    ->dailyAt('03:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();
