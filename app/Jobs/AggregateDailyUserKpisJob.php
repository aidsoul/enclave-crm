<?php

namespace App\Jobs;

use App\Services\DailyKpiService;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateDailyUserKpisJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public int $timeout = 600;

    public int $uniqueFor = 1800;

    public function __construct(
        public ?string $date = null
    ) {
        $this->onConnection('redis-low')->onQueue('low');
    }

    public function uniqueId(): string
    {
        return $this->resolveDateString();
    }

    public function handle(DailyKpiService $service): void
    {
        try {
            $date = $this->date ? Carbon::parse($this->date) : now()->subDay();
        } catch (InvalidFormatException) {
            return;
        }

        $service->aggregateDay($date);
    }

    private function resolveDateString(): string
    {
        try {
            return $this->date ? Carbon::parse($this->date)->toDateString() : now()->subDay()->toDateString();
        } catch (\Throwable) {
            return 'invalid-date';
        }
    }
}
