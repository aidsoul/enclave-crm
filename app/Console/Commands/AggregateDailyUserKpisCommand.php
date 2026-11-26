<?php

namespace App\Console\Commands;

use App\Services\DailyKpiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateDailyUserKpisCommand extends Command
{
    protected $signature = 'kpi:aggregate-daily {--date= : The date to aggregate (YYYY-MM-DD), defaults to yesterday}';

    protected $description = 'Aggregate daily KPI metrics for managers and couriers into daily_user_kpis';

    public function handle(DailyKpiService $service): int
    {
        $dateInput = $this->option('date');
        try {
            $date = $dateInput ? Carbon::parse($dateInput) : Carbon::yesterday();
        } catch (\Throwable $e) {
            $this->error("Invalid date format: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Aggregating KPIs for date: {$date->toDateString()}...");
        $processed = $service->aggregateDay($date);
        $this->info("Aggregated KPIs for {$processed} user records.");

        return self::SUCCESS;
    }
}
