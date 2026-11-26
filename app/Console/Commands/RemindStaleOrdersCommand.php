<?php

namespace App\Console\Commands;

use App\Services\Orders\StaleOrderReminderService;
use Illuminate\Console\Command;

class RemindStaleOrdersCommand extends Command
{
    protected $signature = 'orders:remind-stale {--minutes= : Stale threshold in minutes}';

    protected $description = 'Send Telegram reminders for orders stuck in non-terminal statuses';

    public function handle(StaleOrderReminderService $service): int
    {
        $minutesOption = $this->option('minutes');
        $minutes = $minutesOption !== null ? (int) $minutesOption : null;

        $sentCount = $service->checkAndRemind($minutes);

        $this->info("Sent {$sentCount} reminder(s) for stale orders.");

        return self::SUCCESS;
    }
}
