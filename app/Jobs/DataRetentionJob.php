<?php

namespace App\Jobs;

use App\Enums\SettingKey;
use App\Services\DataRetentionService;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DataRetentionJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onConnection('redis-low')->onQueue('low');
    }

    public function uniqueId(): string
    {
        return 'data-retention';
    }

    public function handle(SettingService $settingService, DataRetentionService $service): void
    {
        $retentionDays = $settingService->get(SettingKey::RETENTION_DAYS, null);

        if ($retentionDays === null || (int) $retentionDays <= 0) {
            return;
        }

        $service->run((int) $retentionDays);
    }
}
