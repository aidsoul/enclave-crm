<?php

namespace App\Services\Couriers;

use App\Enums\ActionType;
use App\Enums\CourierHandoverReason;
use App\Enums\CourierHandoverStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\ActionLog;
use App\Models\CourierHandover;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderAssigner;
use App\Services\TelegramService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourierHandoverService
{
    public function __construct(
        protected OrderAssigner $orderAssigner,
        protected TelegramService $telegramService
    ) {}

    /**
     * Get transferable orders for courier (assigned or in transit).
     *
     * @return Collection<int, Order>
     */
    public function getTransferableOrders(User $courier): Collection
    {
        return Order::where('courier_id', $courier->id)
            ->whereIn('status', [OrderStatus::ASSIGNED, OrderStatus::AT_WAREHOUSE])
            ->with(['customer', 'delivery', 'items'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Calculate current shift cash balance on hand for the courier today.
     * Takes into account collected cash and previous handovers today.
     */
    public function getCurrentShiftCash(User $courier, ?Carbon $date = null): float
    {
        $date = $date ?? Carbon::today();
        $startDate = $date->copy()->startOfDay();
        $endDate = $date->copy()->endOfDay();

        $completedOrders = Order::where('status', OrderStatus::COMPLETED)
            ->whereHas('delivery', fn ($q) => $q->where('user_id', $courier->id))
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->with(['payment'])
            ->get();

        $cashCollected = 0.0;
        foreach ($completedOrders as $ord) {
            $paidPayments = $ord->payment->where('status', PaymentStatus::PAID);
            if ($paidPayments->isNotEmpty()) {
                foreach ($paidPayments as $p) {
                    if ($p->method === PaymentMethod::CASH) {
                        $cashCollected += (float) $p->amount;
                    }
                }
            } else {
                $cashCollected += (float) $ord->total_price;
            }
        }

        // Subtract accepted cash transferred OUT today
        $transferredOut = (float) CourierHandover::where('from_courier_id', $courier->id)
            ->where('transfer_cash', true)
            ->whereIn('status', [CourierHandoverStatus::COMPLETED, CourierHandoverStatus::DISPUTED])
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->sum('actual_cash_amount');

        // Add accepted cash transferred IN today
        $transferredIn = (float) CourierHandover::where('to_courier_id', $courier->id)
            ->where('transfer_cash', true)
            ->whereIn('status', [CourierHandoverStatus::COMPLETED, CourierHandoverStatus::DISPUTED])
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->sum('actual_cash_amount');

        return max(0.0, round($cashCollected - $transferredOut + $transferredIn, 2));
    }

    /**
     * Generate inline SVG string for the QR code.
     */
    public function generateQrSvg(string $code): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(180, 2),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);

        return $writer->writeString($code);
    }

    /**
     * Get active pending handover for courier if one exists.
     */
    public function getActivePendingHandover(User $courier): ?CourierHandover
    {
        return CourierHandover::where('from_courier_id', $courier->id)
            ->where('status', CourierHandoverStatus::PENDING)
            ->where('expires_at', '>', now())
            ->with(['orders.customer', 'orders.delivery'])
            ->latest()
            ->first();
    }

    /**
     * Initiate a new handover session with unique code.
     */
    public function initiateHandover(
        User $fromCourier,
        array $orderIds,
        bool $transferCash,
        float $declaredCash,
        string|CourierHandoverReason $reason,
        ?string $reasonComment = null,
        ?User $actor = null
    ): CourierHandover {
        $actor = $actor ?? $fromCourier;

        if (empty($orderIds)) {
            throw new \InvalidArgumentException('Необходимо выбрать хотя бы один заказ для передачи');
        }

        return DB::transaction(function () use ($fromCourier, $orderIds, $transferCash, $declaredCash, $reason, $reasonComment, $actor) {
            // Cancel any existing pending handovers from this courier
            CourierHandover::where('from_courier_id', $fromCourier->id)
                ->where('status', CourierHandoverStatus::PENDING)
                ->update(['status' => CourierHandoverStatus::CANCELLED]);

            // Lock and verify orders
            $orders = Order::whereIn('id', $orderIds)
                ->where('courier_id', $fromCourier->id)
                ->whereIn('status', [OrderStatus::ASSIGNED, OrderStatus::AT_WAREHOUSE])
                ->lockForUpdate()
                ->get();

            if ($orders->count() !== count($orderIds)) {
                throw new \InvalidArgumentException('Некоторые выбранные заказы уже недоступны или изменены');
            }

            $systemCash = $transferCash ? $this->getCurrentShiftCash($fromCourier) : 0.00;
            $reasonEnum = $reason instanceof CourierHandoverReason ? $reason : CourierHandoverReason::from($reason);

            // Generate unique, unambiguous 6-char code (omitting confusing 0/O, 1/I/L)
            $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
            do {
                $suffix = '';
                for ($i = 0; $i < 6; $i++) {
                    $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $code = 'FM-'.$suffix;
            } while (CourierHandover::where('transfer_code', $code)->exists());

            $handover = CourierHandover::create([
                'transfer_code' => $code,
                'from_courier_id' => $fromCourier->id,
                'initiated_by_user_id' => $actor->id,
                'status' => CourierHandoverStatus::PENDING,
                'reason' => $reasonEnum,
                'reason_comment' => $reasonComment,
                'transfer_cash' => $transferCash,
                'system_cash_amount' => $systemCash,
                'declared_cash_amount' => $transferCash ? max(0.0, round($declaredCash, 2)) : 0.00,
                'actual_cash_amount' => 0.00,
                'discrepancy' => 0.00,
                'expires_at' => now()->addMinutes(30),
            ]);

            foreach ($orders as $order) {
                $handover->orders()->attach($order->id, [
                    'previous_status' => $order->status->value,
                ]);
            }

            return $handover->load(['orders.customer', 'orders.delivery', 'fromCourier']);
        });
    }

    /**
     * Cancel pending handover.
     */
    public function cancelHandover(int $handoverId, User $actor): CourierHandover
    {
        return DB::transaction(function () use ($handoverId, $actor) {
            $handover = CourierHandover::where('id', $handoverId)
                ->where('status', CourierHandoverStatus::PENDING)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $actor->isOwner() && ! $actor->isManager() && $actor->id !== $handover->from_courier_id) {
                throw new \InvalidArgumentException('У вас нет прав для отмены этой передачи');
            }

            $handover->update([
                'status' => CourierHandoverStatus::CANCELLED,
            ]);

            return $handover;
        });
    }

    /**
     * Get preview of a handover by transfer code for receiving courier.
     */
    public function getPreview(string $code, User $toCourier): array
    {
        $code = trim(strtoupper($code));

        $handover = CourierHandover::where('transfer_code', $code)
            ->with(['fromCourier', 'orders.customer', 'orders.delivery', 'orders.items'])
            ->firstOrFail();

        if ($handover->status !== CourierHandoverStatus::PENDING) {
            throw new \InvalidArgumentException('Эта передача уже завершена или отменена');
        }

        if ($handover->expires_at->isPast()) {
            $handover->update(['status' => CourierHandoverStatus::CANCELLED]);
            throw new \InvalidArgumentException('Срок действия кода передачи истёк');
        }

        if ($handover->from_courier_id === $toCourier->id) {
            throw new \InvalidArgumentException('Нельзя принять передачу от самого себя');
        }

        return [
            'handover' => $handover,
            'from_courier' => [
                'id' => $handover->fromCourier->id,
                'name' => $handover->fromCourier->system_name,
            ],
            'reason_label' => $handover->reason->label(),
            'reason_comment' => $handover->reason_comment,
            'orders' => $handover->orders->map(fn ($ord) => [
                'id' => $ord->id,
                'customer_name' => $ord->customer?->name ?? '—',
                'address' => $ord->delivery->first()?->delivery_address ?: ($ord->customer?->address ?? '—'),
                'total_price' => (float) $ord->total_price,
                'items_count' => $ord->items->count(),
            ]),
            'transfer_cash' => $handover->transfer_cash,
            'declared_cash_amount' => (float) $handover->declared_cash_amount,
            'expires_at' => $handover->expires_at->toIso8601String(),
            'qr_svg' => $this->generateQrSvg($handover->transfer_code),
        ];
    }

    /**
     * Accept handover and reconcile cash.
     */
    public function acceptHandover(
        string $code,
        User $toCourier,
        float $actualCashAmount,
        ?string $discrepancyReason = null,
        ?string $discrepancyPhoto = null
    ): CourierHandover {
        $code = trim(strtoupper($code));

        return DB::transaction(function () use ($code, $toCourier, $actualCashAmount, $discrepancyReason, $discrepancyPhoto) {
            $handover = CourierHandover::where('transfer_code', $code)
                ->where('status', CourierHandoverStatus::PENDING)
                ->lockForUpdate()
                ->firstOrFail();

            if ($handover->expires_at->isPast()) {
                $handover->update(['status' => CourierHandoverStatus::CANCELLED]);
                throw new \InvalidArgumentException('Срок действия передачи истёк');
            }

            if ($handover->from_courier_id === $toCourier->id) {
                throw new \InvalidArgumentException('Нельзя принять передачу от самого себя');
            }

            // Lock and validate orders to prevent race conditions (e.g. order cancelled while handover pending)
            $orders = $handover->orders()->lockForUpdate()->get();
            $validOrdersToAssign = [];
            foreach ($orders as $order) {
                if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::COMPLETED], true)) {
                    Log::warning('Handover order skipped because status is terminal', [
                        'handover_id' => $handover->id,
                        'order_id' => $order->id,
                        'status' => $order->status->value,
                    ]);
                    continue;
                }
                if ((int) $order->courier_id !== (int) $handover->from_courier_id) {
                    Log::warning('Handover order skipped because courier_id changed', [
                        'handover_id' => $handover->id,
                        'order_id' => $order->id,
                        'expected_courier_id' => $handover->from_courier_id,
                        'actual_courier_id' => $order->courier_id,
                    ]);
                    continue;
                }
                $validOrdersToAssign[] = $order;
            }

            if (empty($validOrdersToAssign) && $orders->isNotEmpty()) {
                throw new \InvalidArgumentException('Все заказы из этой передачи уже выполнены или отменены');
            }

            $declaredCash = (float) $handover->declared_cash_amount;
            $actualCash = $handover->transfer_cash ? max(0.0, round($actualCashAmount, 2)) : 0.00;
            $discrepancy = $handover->transfer_cash ? round($actualCash - $declaredCash, 2) : 0.00;

            $status = (abs($discrepancy) > 0.009)
                ? CourierHandoverStatus::DISPUTED
                : CourierHandoverStatus::COMPLETED;

            $handover->update([
                'to_courier_id' => $toCourier->id,
                'status' => $status,
                'actual_cash_amount' => $actualCash,
                'discrepancy' => $discrepancy,
                'discrepancy_reason' => $discrepancyReason,
                'discrepancy_photo' => $discrepancyPhoto,
                'completed_at' => now(),
            ]);

            // Reassign valid orders to the receiving courier
            foreach ($validOrdersToAssign as $order) {
                $this->orderAssigner->assignCourier(
                    orderId: $order->id,
                    courierId: $toCourier->id,
                    actorId: $toCourier->id,
                    courier: $toCourier,
                    allowReassign: true
                );

                ActionLog::create([
                    'actor_id' => $toCourier->id,
                    'order_id' => $order->id,
                    'site_id' => $order->site_id,
                    'action_type' => ActionType::ASSIGN,
                    'field_name' => 'courier_handover',
                    'old_value' => (string) $handover->from_courier_id,
                    'new_value' => (string) $toCourier->id,
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'created_at' => now(),
                ]);
            }

            $handover->load(['fromCourier', 'toCourier', 'orders']);

            // Send Telegram notifications
            try {
                $this->telegramService->notifyAboutCourierHandover($handover);
            } catch (\Throwable $e) {
                Log::channel('webhooks')->error('Failed to send handover telegram notification', [
                    'handover_id' => $handover->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $handover;
        });
    }
}
