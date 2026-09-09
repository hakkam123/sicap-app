<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (Illuminate\Support\Facades\Schema::hasTable('sync_schedules')) {
    try {
        $schedules = \App\Models\SyncSchedule::where('is_active', true)->get();
        foreach ($schedules as $schedule) {
            if (!empty($schedule->time)) {
                Schedule::command('sicap:sync-api')
                    ->dailyAt($schedule->time)
                    ->timezone('Asia/Jakarta')
                    ->withoutOverlapping();
            }
        }
    } catch (\Throwable $e) {
        // Fallback to default schedules if query fails
        Schedule::command('sicap:sync-api')->dailyAt('05:00')->timezone('Asia/Jakarta')->withoutOverlapping();
        Schedule::command('sicap:sync-api')->dailyAt('11:00')->timezone('Asia/Jakarta')->withoutOverlapping();
        Schedule::command('sicap:sync-api')->dailyAt('17:00')->timezone('Asia/Jakarta')->withoutOverlapping();
    }
}


