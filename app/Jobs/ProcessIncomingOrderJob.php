<?php

namespace App\Jobs;

use App\Dto\Order\CreateOrderDto;
use App\Models\Site;
use App\Services\Orders\OrderCreator;
use App\Support\PhoneNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessIncomingOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public int $timeout = 120;

    public function __construct(
        public int $siteId,
        public array $validatedData
    ) {
        $this->onQueue('high');
    }

    public function handle(OrderCreator $orderCreator): void
    {
        $externalId = $this->validatedData['external_id'] ?? ($this->validatedData['external_order_id'] ?? null);
        $phone = $this->validatedData['client_phone'] ?? ($this->validatedData['client_data']['phone'] ?? null);
        $totalPrice = $orderCreator->calculateTotalPrice($this->validatedData);
        $dedupMinutes = (int) config('orders.dedup_minutes', 20);

        $lockKey = null;
        if ($externalId) {
            $lockKey = "lock:incoming_order:{$this->siteId}:ext:{$externalId}";
        } elseif ($phone) {
            $normalizedPhone = PhoneNumber::normalize($phone) ?: preg_replace('/\D+/', '', $phone);
            $lockKey = "lock:incoming_order:{$this->siteId}:{$normalizedPhone}:".number_format($totalPrice, 2, '.', '');
        }
        $lock = $lockKey ? Cache::lock($lockKey, 15) : null;

        try {
            $site = Site::find($this->siteId);

            if (! $site) {
                Log::channel('webhooks')->warning('ProcessIncomingOrderJob: site not found, skipping', [
                    'site_id' => $this->siteId,
                ]);

                return;
            }

            if ($lock) {
                $lock->block(10);
            }

            $externalId = $this->validatedData['external_id'] ?? ($this->validatedData['external_order_id'] ?? null);

            $duplicate = $orderCreator->findRecentDuplicate(
                $this->siteId,
                $phone,
                $totalPrice,
                $dedupMinutes,
                $externalId ? (string) $externalId : null
            );

            if ($duplicate) {
                Log::channel('webhooks')->info('Incoming order skipped as duplicate', [
                    'site_id' => $this->siteId,
                    'order_id' => $duplicate->id,
                    'external_id' => $externalId,
                ]);

                return;
            }

            $dto = CreateOrderDto::fromArray($this->siteId, $this->validatedData);
            $order = $orderCreator->createFromDto($dto);

            $isSuspicious = (bool) ($this->validatedData['extra_data']['_antifraud']['is_suspicious'] ?? false);
            if (! $isSuspicious) {
                SendOrderNotificationJob::dispatch($order);
            } else {
                Log::channel('webhooks')->info('Skipping manager notification for suspicious order', [
                    'order_id' => $order->id,
                    'site_id' => $this->siteId,
                    'antifraud' => $this->validatedData['extra_data']['_antifraud'] ?? [],
                ]);
            }

            Log::channel('webhooks')->info('Incoming order processed', [
                'order_id' => $order->id,
                'site_id' => $this->siteId,
            ]);
        } catch (\Exception $e) {
            Log::channel('webhooks')->error('Incoming order failed', [
                'site_id' => $this->siteId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $lock?->release();
        }
    }
}
