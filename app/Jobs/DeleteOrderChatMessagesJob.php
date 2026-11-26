<?php

namespace App\Jobs;

use App\Enums\SettingKey;
use App\Services\SettingService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteOrderChatMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public int $orderId
    ) {
        $this->onQueue('notifications');
    }

    public function handle(TelegramService $telegramService, SettingService $settingService): void
    {
        $enabled = (bool) $settingService->get(SettingKey::DELETE_CHAT_MESSAGES_ON_ACCEPT, null);

        Log::channel('webhooks')->info('DeleteOrderChatMessagesJob: executing', [
            'order_id' => $this->orderId,
            'enabled' => $enabled,
        ]);

        if (! $enabled) {
            return;
        }

        $telegramService->deleteOrderMessages($this->orderId);
    }
}
