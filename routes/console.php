<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sicap:sync-api')
    ->dailyAt('05:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

Schedule::command('sicap:sync-api')
    ->dailyAt('11:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

Schedule::command('sicap:sync-api')
    ->dailyAt('17:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

