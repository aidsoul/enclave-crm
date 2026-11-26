<?php

namespace App\Console\Commands;

use App\Services\DailyKpiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillDailyUserKpisCommand extends Command
{
    protected $signature = 'kpi:backfill {--from= : Start date (YYYY-MM-DD)} {--to= : End date (YYYY-MM-DD)}';

    protected $description = 'Backfill historical daily KPI aggregates into daily_user_kpis';

    public function handle(DailyKpiService $service): int
    {
        $fromInput = $this->option('from');
        $toInput = $this->option('to');

        if (! $fromInput) {
            $this->error('The --from option is required.');

            return self::FAILURE;
        }

        try {
            $from = Carbon::parse($fromInput);
            $to = $toInput ? Carbon::parse($toInput) : Carbon::yesterday();
        } catch (\Throwable $e) {
            $this->error("Invalid date format: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Backfilling daily KPIs from {$from->toDateString()} to {$to->toDateString()}...");
        $days = $service->backfill($from, $to);
        $this->info("Successfully backfilled {$days} day(s).");

        return self::SUCCESS;
    }
}
