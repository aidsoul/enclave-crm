<?php

namespace App\Http\Controllers;

use App\Services\Couriers\CourierHandoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourierHandoverController extends Controller
{
    public function __construct(
        protected CourierHandoverService $handoverService
    ) {}

    /**
     * Get transferable orders and current shift cash balance.
     */
    public function orders(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isCourier() && ! $user->isManager() && ! $user->isOwner()) {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        $targetUser = $user;

        // Managers/owners can inspect a courier's transferable orders
        if (($user->isOwner() || $user->isManager()) && $request->filled('courier_id')) {
            $targetUser = \App\Models\User::findOrFail((int) $request->query('courier_id'));
        }

        if (! $targetUser->isCourier()) {
            return response()->json(['message' => 'Выбранный пользователь не является курьером'], 422);
        }

        $orders = $this->handoverService->getTransferableOrders($targetUser);
        $currentCash = $this->handoverService->getCurrentShiftCash($targetUser);

        $formattedOrders = $orders->map(function ($order) {
            $del = $order->delivery->first();

            return [
                'id' => $order->id,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'customer_name' => $order->customer?->name ?? '—',
                'customer_phone' => $order->customer?->formatted_phone ?: ($order->customer?->phone ?? '—'),
                'address' => $del?->delivery_address ?: ($order->customer?->address ?? '—'),
                'delivery_slot' => $del?->delivery_slot,
                'delivery_date' => $del?->delivery_date,
                'total_price' => (float) $order->total_price,
                'items_count' => $order->items->count(),
            ];
        });

        return response()->json([
            'orders' => $formattedOrders,
            'current_cash' => $currentCash,
        ]);
    }

    /**
     * Get currently active pending handover if one exists.
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isCourier()) {
            return response()->json(['handover' => null]);
        }

        $handover = $this->handoverService->getActivePendingHandover($user);

        if (! $handover) {
            return response()->json(['handover' => null]);
        }

        return response()->json([
            'handover' => [
                'id' => $handover->id,
                'transfer_code' => $handover->transfer_code,
                'qr_svg' => $this->handoverService->generateQrSvg($handover->transfer_code),
                'reason' => $handover->reason->value,
                'reason_label' => $handover->reason->label(),
                'reason_comment' => $handover->reason_comment,
                'transfer_cash' => $handover->transfer_cash,
                'declared_cash_amount' => (float) $handover->declared_cash_amount,
                'expires_at' => $handover->expires_at->toIso8601String(),
                'orders_count' => $handover->orders->count(),
                'orders' => $handover->orders->map(fn ($o) => [
                    'id' => $o->id,
                    'address' => $o->delivery->first()?->delivery_address ?: ($o->customer?->address ?? '—'),
                    'total_price' => (float) $o->total_price,
                ]),
            ],
        ]);
    }

    /**
     * Initiate a new handover session.
     */
    public function initiate(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isCourier() && ! $user->isManager() && ! $user->isOwner()) {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        $validated = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'required|integer|exists:orders,id',
            'transfer_cash' => 'required|boolean',
            'declared_cash' => 'nullable|numeric|min:0',
            'reason' => 'required|string|in:vehicle_breakdown,accident,health,other',
            'reason_comment' => 'nullable|string|max:500',
            'courier_id' => 'nullable|integer|exists:users,id',
        ]);

        $fromCourier = $user;

        // Managers or owners can initiate on behalf of a courier in an emergency
        if (($user->isOwner() || $user->isManager()) && ! empty($validated['courier_id'])) {
            $fromCourier = \App\Models\User::findOrFail((int) $validated['courier_id']);
        }

        if (! $fromCourier->isCourier()) {
            return response()->json(['message' => 'Пользователь не является курьером'], 422);
        }

        try {
            $handover = $this->handoverService->initiateHandover(
                fromCourier: $fromCourier,
                orderIds: $validated['order_ids'],
                transferCash: (bool) $validated['transfer_cash'],
                declaredCash: (float) ($validated['declared_cash'] ?? 0),
                reason: $validated['reason'],
                reasonComment: $validated['reason_comment'] ?? null,
                actor: $user
            );

            return response()->json([
                'success' => true,
                'handover' => [
                    'id' => $handover->id,
                    'transfer_code' => $handover->transfer_code,
                    'qr_svg' => $this->handoverService->generateQrSvg($handover->transfer_code),
                    'expires_at' => $handover->expires_at->toIso8601String(),
                    'orders_count' => $handover->orders->count(),
                    'declared_cash_amount' => (float) $handover->declared_cash_amount,
                    'transfer_cash' => $handover->transfer_cash,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel an active pending handover.
     */
    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'handover_id' => 'required|integer|exists:courier_handovers,id',
        ]);

        try {
            $this->handoverService->cancelHandover((int) $validated['handover_id'], $request->user());

            return response()->json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Preview handover by code for receiving courier.
     */
    public function preview(Request $request, string $code): JsonResponse
    {
        try {
            $preview = $this->handoverService->getPreview($code, $request->user());

            return response()->json($preview);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Код передачи не найден'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Accept handover and reconcile cash.
     */
    public function accept(Request $request): JsonResponse
    {
        if (! $request->user()->isCourier()) {
            return response()->json(['message' => 'Принимать заказы на доставку может только курьер'], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string',
            'actual_cash' => 'nullable|numeric|min:0',
            'discrepancy_reason' => 'nullable|string|max:500',
            'discrepancy_photo' => 'nullable|string|max:255',
        ]);

        try {
            $handover = $this->handoverService->acceptHandover(
                code: $validated['code'],
                toCourier: $request->user(),
                actualCashAmount: (float) ($validated['actual_cash'] ?? 0),
                discrepancyReason: $validated['discrepancy_reason'] ?? null,
                discrepancyPhoto: $validated['discrepancy_photo'] ?? null
            );

            return response()->json([
                'success' => true,
                'status' => $handover->status->value,
                'status_label' => $handover->status->label(),
                'discrepancy' => (float) $handover->discrepancy,
                'orders_reassigned' => $handover->orders->count(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Код передачи не найден'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
