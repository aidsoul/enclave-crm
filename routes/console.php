<?php

use App\Jobs\AggregateDailyUserKpisJob;
use App\Jobs\AggregateFinancialStatsJob;
use App\Jobs\CheckStaleOrdersJob;
use App\Jobs\DataRetentionJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DataRetentionJob)->dailyAt('03:00')->withoutOverlapping(60);
Schedule::job(new AggregateFinancialStatsJob)->dailyAt('04:00')->withoutOverlapping(60);
Schedule::job(new AggregateDailyUserKpisJob)->dailyAt('00:05')->withoutOverlapping(60);
if (config('backup.schedule.enabled', true)) {
    Schedule::command('backup:clean')
        ->dailyAt(config('backup.schedule.clean_at', '01:00'))
        ->withoutOverlapping(60);

    Schedule::command('backup:run')
        ->dailyAt(config('backup.schedule.run_at', '02:00'))
        ->withoutOverlapping(120);
}
Schedule::job(new CheckStaleOrdersJob)->everyThirtyMinutes()->withoutOverlapping(60);
