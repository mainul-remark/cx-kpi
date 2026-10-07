<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run every day at midnight to auto-expire KV assignments
Schedule::command('kv:expire-assignments')->dailyAt('00:00');

// Run on the 1st of every month to freeze the KPI scores of the month that just ended
Schedule::command('kpi:snapshot')->monthlyOn(1, '00:30');
