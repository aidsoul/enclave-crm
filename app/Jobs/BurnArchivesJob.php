<?php

namespace App\Jobs;

use App\Services\DataRetentionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BurnArchivesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $actorId
    ) {
        $this->onConnection('redis-low')->onQueue('low');
    }

    public function handle(DataRetentionService $service): void
    {
        $service->burnEverything($this->actorId);
    }
}
