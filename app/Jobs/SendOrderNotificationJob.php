<?php

namespace App\Jobs;

use App\Events\OrderCreated;
use App\Models\Order;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    public int $backoff = 5;

    public int $timeout = 60;

    public function __construct(
        public Order $order
    ) {
        $this->onQueue('notifications');
    }

    public function handle(TelegramService $telegramService): void
    {
        Log::channel('webhooks')->info('SendOrderNotificationJob: start', [
            'order_id' => $this->order->id,
        ]);

        if ($this->order->site?->is_active) {
            event(new OrderCreated($this->order));
        }

        $token = config('nutgram.token');

        if (! $token) {
            Log::channel('webhooks')->error('SendOrderNotificationJob: nutgram.token is empty', [
                'order_id' => $this->order->id,
            ]);

            return;
        }

        try {
            $telegramService->notifyManagersAboutNewOrder($this->order);
            Log::channel('webhooks')->info('SendOrderNotificationJob: done', [
                'order_id' => $this->order->id,
            ]);
        } catch (\Throwable $e) {
            Log::channel('webhooks')->error('SendOrderNotificationJob: failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 500),
            ]);
        }
    }
}
