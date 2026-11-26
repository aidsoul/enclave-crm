<?php

namespace App\Jobs;

use App\Models\OrderMessage;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;

class NotifyOrderChatMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    public int $backoff = 5;

    public int $timeout = 60;

    public function __construct(public OrderMessage $message)
    {
        $this->onQueue('notifications');
    }

    public function handle(TelegramService $telegramService): void
    {
        $token = config('nutgram.token');

        if (! $token) {
            return;
        }

        try {
            $telegramService->notifyAboutChatMessage($this->message);
        } catch (TelegramException $e) {
            Log::warning('Telegram chat notification failed: '.$e->getMessage(), [
                'message_id' => $this->message->id,
                'order_id' => $this->message->order_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error processing NotifyOrderChatMessageJob: '.$e->getMessage(), [
                'message_id' => $this->message->id,
                'order_id' => $this->message->order_id,
            ]);
        }
    }
}
