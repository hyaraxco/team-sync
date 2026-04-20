<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance-periods:sync')->dailyAt('00:10');
Schedule::command('attendance-mismatches:escalate')->dailyAt('00:20');
Schedule::command('analytics:calculate-daily-snapshots')->dailyAt('00:30');
Schedule::command('analytics:calculate-daily-metrics')->dailyAt('00:40');
