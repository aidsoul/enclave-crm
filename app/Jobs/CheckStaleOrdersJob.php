<?php

namespace App\Jobs;

use App\Services\Orders\StaleOrderReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckStaleOrdersJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct()
    {
        $this->onConnection('redis-low')->onQueue('low');
    }

    public function uniqueId(): string
    {
        return 'check-stale-orders';
    }

    public function handle(StaleOrderReminderService $service): void
    {
        $service->checkAndRemind();
    }
}
