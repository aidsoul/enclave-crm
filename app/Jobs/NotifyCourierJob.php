<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;

class NotifyCourierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    public int $backoff = 5;

    public int $timeout = 60;

    public function __construct(
        public Order $order,
        public User $courier
    ) {
        $this->onQueue('notifications');
    }

    public function handle(TelegramService $telegramService): void
    {
        $token = config('nutgram.token');

        if (! $token) {
            Log::warning('Telegram notification skipped: bot token is not configured', [
                'order_id' => $this->order->id,
                'courier_id' => $this->courier->id,
            ]);

            return;
        }

        try {
            $telegramService->notifyCourierAboutAssignment($this->order, $this->courier);
        } catch (TelegramException $e) {
            $code = (int) $e->getCode();
            $message = (string) $e->getMessage();

            if ($code === 403 || $code === 400 || str_contains($message, 'blocked') || str_contains($message, 'chat not found')) {
                Log::warning('Telegram notification skipped: bot blocked or chat not found', [
                    'order_id' => $this->order->id,
                    'courier_id' => $this->courier->id,
                    'telegram_user_id' => $this->courier->telegram_user_id,
                    'error' => $message,
                    'code' => $code,
                ]);

                return;
            }

            if ($code === 429 || str_contains($message, 'Too Many Requests')) {
                $retryAfter = (int) $e->getParameter('retry_after', 30);
                if ($retryAfter <= 0 && preg_match('/retry after (\d+)/i', $message, $matches)) {
                    $retryAfter = (int) $matches[1];
                }
                $retryAfter = max($retryAfter, 5);

                Log::warning('Telegram rate limit hit, releasing courier notification job', [
                    'order_id' => $this->order->id,
                    'courier_id' => $this->courier->id,
                    'retry_after' => $retryAfter,
                ]);

                $this->release($retryAfter);

                return;
            }

            Log::warning('Failed to send Telegram notification to courier', [
                'order_id' => $this->order->id,
                'courier_id' => $this->courier->id,
                'telegram_user_id' => $this->courier->telegram_user_id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Unexpected error sending Telegram notification to courier', [
                'order_id' => $this->order->id,
                'courier_id' => $this->courier->id,
                'telegram_user_id' => $this->courier->telegram_user_id,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            throw $e;
        }
    }
}
