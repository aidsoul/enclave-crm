<?php

namespace App\Jobs;

use App\Services\DataRetentionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class PurgeAllBackupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onConnection('redis')->onQueue('high');
    }

    public function handle(DataRetentionService $service): void
    {
        $service->purgeAllBackups();
    }
}
