<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Periodically recover stuck AI processing jobs (e.g. crashed workers).
Schedule::command('queue:retry all')->dailyAt('03:00')->withoutOverlapping();

// Keep the async AI pipeline healthy: any job stuck in "processing" for >30 min gets reset to pending.
Schedule::call(function () {
    \App\Models\ProcessingJob::where('status', \App\Models\ProcessingJob::STATUS_PROCESSING)
        ->where('updated_at', '<', now()->subMinutes(30))
        ->update(['status' => \App\Models\ProcessingJob::STATUS_PENDING]);
})->everyFiveMinutes();

