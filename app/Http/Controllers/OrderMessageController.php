<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Enums\OrderStatus;
use App\Events\OrderMessageDeleted;
use App\Events\OrderMessageSent;
use App\Events\OrderMessageUpdated;
use App\Http\Requests\StoreOrderMessageRequest;
use App\Http\Resources\OrderMessageResource;
use App\Jobs\NotifyOrderChatMessageJob;
use App\Models\ActionLog;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Services\RoleService;
use App\Support\StrHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderMessageController extends Controller
{
    public function __construct(protected RoleService $roleService) {}

    public function unreadSummary(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($user->isCourier()) {
            $baseQuery = Order::forCourier($user->id);
            $unreadCount = (int) (clone $baseQuery)->where('unread_manager_messages_count', '>', 0)
                ->sum('unread_manager_messages_count');
            $latestOrderId = $unreadCount > 0
                ? (clone $baseQuery)->where('unread_manager_messages_count', '>', 0)->orderByDesc('last_message_at')->value('id')
                : (clone $baseQuery)->orderByDesc('id')->value('id');
        } elseif ($user->isManager()) {
            $baseQuery = Order::where('manager_id', $user->id);
            $unreadCount = (int) (clone $baseQuery)->where('unread_driver_messages_count', '>', 0)
                ->sum('unread_driver_messages_count');
            $latestOrderId = $unreadCount > 0
                ? (clone $baseQuery)->where('unread_driver_messages_count', '>', 0)->orderByDesc('last_message_at')->value('id')
                : (clone $baseQuery)->orderByDesc('id')->value('id');
        } elseif ($user->isOwner()) {
            $baseQuery = Order::query();
            $unreadCount = (int) (clone $baseQuery)->where('unread_driver_messages_count', '>', 0)
                ->sum('unread_driver_messages_count');
            $latestOrderId = $unreadCount > 0
                ? (clone $baseQuery)->where('unread_driver_messages_count', '>', 0)->orderByDesc('last_message_at')->value('id')
                : (clone $baseQuery)->orderByDesc('id')->value('id');
        } else {
            return response()->json([
                'unread_count' => 0,
                'latest_order_id' => null,
            ]);
        }

        return response()->json([
            'unread_count' => $unreadCount,
            'latest_order_id' => $latestOrderId,
        ]);
    }

    public function activeChatOrders(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $limit = min(max((int) $request->query('limit', 4), 1), 10);

        $query = Order::query()
            ->with(['customer:id,name'])
            ->select([
                'orders.id',
                'orders.customer_id',
                'orders.status',
                'orders.last_message_at',
                'orders.unread_driver_messages_count',
                'orders.unread_manager_messages_count',
            ]);

        if ($user->isCourier()) {
            $query->forCourier($user->id)
                ->where('orders.status', '!=', OrderStatus::CANCELLED);
        } elseif ($user->isManager()) {
            $query->forManager($user->id)
                ->where('orders.status', '!=', OrderStatus::CANCELLED);
        } elseif ($user->isOwner()) {
            $query->where('orders.status', '!=', OrderStatus::CANCELLED);
        } else {
            return response()->json(['orders' => []]);
        }

        $orders = $query
            ->orderByRaw('orders.last_message_at DESC NULLS LAST')
            ->orderByDesc('orders.id')
            ->limit($limit)
            ->get();

        $items = $orders->map(function ($order) use ($user) {
            $unread = $user->isCourier()
                ? ($order->unread_manager_messages_count ?? 0)
                : ($order->unread_driver_messages_count ?? 0);

            return [
                'id' => $order->id,
                'customer_name' => $order->customer?->name,
                'status' => $order->status?->value ?? (string) $order->status,
                'unread_count' => $unread,
                'last_message_at' => $order->last_message_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'orders' => $items,
        ]);
    }

    public function searchChatOrders(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $rawQ = trim((string) $request->query('q', ''));
        $q = ltrim($rawQ, '#');
        $limit = min(max((int) $request->query('limit', 6), 1), 15);

        if ($q === '') {
            return response()->json(['orders' => []]);
        }

        $query = Order::query()
            ->with(['customer:id,name'])
            ->select([
                'orders.id',
                'orders.customer_id',
                'orders.status',
                'orders.last_message_at',
                'orders.unread_driver_messages_count',
                'orders.unread_manager_messages_count',
            ]);

        if ($user->isCourier()) {
            $query->forCourier($user->id);
        } elseif ($user->isManager()) {
            $query->forManager($user->id);
        } elseif (! $user->isOwner()) {
            return response()->json(['orders' => []]);
        }

        if (ctype_digit($q)) {
            $query->whereRaw('CAST(orders.id AS TEXT) LIKE ?', ["{$q}%"]);
        } else {
            $searchTerm = StrHelper::escapeLike(mb_strtolower($q));
            $query->whereHas('customer', function ($cq) use ($searchTerm) {
                $cq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
            });
        }

        $orders = $query
            ->orderByRaw('orders.last_message_at DESC NULLS LAST')
            ->orderByDesc('orders.id')
            ->limit($limit)
            ->get();

        $items = $orders->map(function ($order) use ($user) {
            $unread = $user->isCourier()
                ? ($order->unread_manager_messages_count ?? 0)
                : ($order->unread_driver_messages_count ?? 0);

            return [
                'id' => $order->id,
                'customer_name' => $order->customer?->name,
                'status' => $order->status?->value ?? (string) $order->status,
                'unread_count' => $unread,
                'last_message_at' => $order->last_message_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'orders' => $items,
        ]);
    }

    public function index(Request $request, Order $order): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();
        if ($user === null || ! $this->roleService->canAccessOrderChat($user, $order)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $channel = $request->input('channel', 'courier');
        if ($user->isCourier()) {
            if ($channel !== 'courier') {
                return response()->json(['error' => 'Forbidden'], 403);
            }
            if ($order->has_unread_manager_message || $order->unread_manager_messages_count > 0) {
                $order->update([
                    'has_unread_manager_message' => false,
                    'unread_manager_messages_count' => 0,
                ]);
            }
        } elseif ($channel === 'courier' && ($order->has_unread_driver_message || $order->unread_driver_messages_count > 0)) {
            $order->update([
                'has_unread_driver_message' => false,
                'unread_driver_messages_count' => 0,
            ]);
        }

        $limit = min(max((int) $request->input('limit', 30), 1), 50);
        $beforeId = $request->input('before_id');

        $query = OrderMessage::with('user')
            ->where('order_id', $order->id)
            ->where('channel', $channel);

        if ($beforeId) {
            $query->where('id', '<', (int) $beforeId);
        }

        $items = $query->orderByDesc('id')
            ->limit($limit)
            ->get();

        $hasMore = $items->count() === $limit;
        $oldestId = $items->last()?->id;

        $messages = $items->reverse()->values();

        return response()->json([
            'messages' => OrderMessageResource::collection($messages),
            'has_more' => $hasMore,
            'oldest_id' => $oldestId,
        ]);
    }

    public function store(StoreOrderMessageRequest $request, Order $order): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();
        if ($user === null || ! $this->roleService->canAccessOrderChat($user, $order)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ($order->status === OrderStatus::COMPLETED) {
            return response()->json([
                'error' => 'Чат закрыт для новых сообщений, так как заказ завершён',
            ], 422);
        }

        $channel = $request->input('channel', 'courier');
        if ($user->isCourier()) {
            $channel = 'courier';
        }

        try {
            $orderMessage = DB::transaction(function () use ($order, $user, $channel, $request) {
                $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->status === OrderStatus::COMPLETED) {
                    throw new \DomainException('Чат закрыт для новых сообщений, так как заказ завершён');
                }

                $msg = OrderMessage::create([
                    'order_id' => $lockedOrder->id,
                    'user_id' => $user->id,
                    'channel' => $channel,
                    'message' => (string) ($request->input('message') ?? ''),
                ]);

                $updates = [
                    'last_message_at' => now(),
                ];
                if ($channel === 'courier') {
                    if ($user->isCourier()) {
                        $updates['has_unread_driver_message'] = true;
                        $updates['unread_driver_messages_count'] = ($lockedOrder->unread_driver_messages_count ?? 0) + 1;
                    } else {
                        $updates['has_unread_manager_message'] = true;
                        $updates['unread_manager_messages_count'] = ($lockedOrder->unread_manager_messages_count ?? 0) + 1;
                    }
                }
                $lockedOrder->update($updates);

                return $msg;
            });
        } catch (\DomainException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }

        OrderMessageSent::dispatch($orderMessage);
        NotifyOrderChatMessageJob::dispatch($orderMessage);

        return (new OrderMessageResource($orderMessage->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Order $order, OrderMessage $message): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();
        if ($user === null || ! $this->roleService->canAccessOrderChat($user, $order)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ((int) $message->order_id !== (int) $order->id) {
            return response()->json(['error' => 'Message does not belong to this order'], 404);
        }

        if (! $message->canBeEditedBy($user)) {
            if ($order->status === OrderStatus::COMPLETED && ! $user->isOwner()) {
                return response()->json(['error' => 'Заказ завершён. Редактирование сообщений доступно только владельцу'], 403);
            }
            if ($user->isCourier()) {
                return response()->json(['error' => 'Курьер не имеет прав на редактирование сообщений'], 403);
            }
            if ($user->isManager()) {
                if ((int) $message->user_id !== (int) $user->id) {
                    return response()->json(['error' => 'Вы можете редактировать только свои сообщения'], 403);
                }
                if ($message->created_at && $message->created_at->diffInMinutes(now()) > 10) {
                    return response()->json(['error' => 'Время редактирования истекло (доступно в течение 10 минут)'], 403);
                }
            }

            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ], [
            'message.required' => 'Сообщение не может быть пустым.',
            'message.string' => 'Сообщение должно быть строкой.',
            'message.max' => 'Сообщение не должно превышать 2000 символов.',
        ]);

        $newMessage = trim(strip_tags((string) $validated['message']));
        if ($newMessage === '') {
            return response()->json(['error' => 'Сообщение не может быть пустым'], 422);
        }

        $oldMessage = (string) $message->message;

        if ($newMessage === trim($oldMessage)) {
            return (new OrderMessageResource($message->load('user')))->response();
        }

        $message->update([
            'message' => $newMessage,
            'edited_at' => now(),
        ]);

        ActionLog::create([
            'actor_id' => $user->id,
            'order_id' => $order->id,
            'action_type' => ActionType::UPDATE,
            'field_name' => 'chat_message',
            'old_value' => $oldMessage,
            'new_value' => $newMessage,
            'created_at' => now(),
        ]);

        OrderMessageUpdated::dispatch($message);

        return (new OrderMessageResource($message->load('user')))->response();
    }

    public function destroy(Request $request, Order $order, OrderMessage $message): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();
        if ($user === null || ! $this->roleService->canAccessOrderChat($user, $order)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ((int) $message->order_id !== (int) $order->id) {
            return response()->json(['error' => 'Message does not belong to this order'], 404);
        }

        if (! $message->canBeDeletedBy($user)) {
            return response()->json(['error' => 'Удаление сообщений доступно только администратору'], 403);
        }

        $deletedText = (string) $message->message;

        ActionLog::create([
            'actor_id' => $user->id,
            'order_id' => $order->id,
            'action_type' => ActionType::DELETE,
            'field_name' => 'chat_message',
            'old_value' => $deletedText,
            'new_value' => null,
            'created_at' => now(),
        ]);

        OrderMessageDeleted::dispatch($message->id, $order->id, $message->channel);

        $message->delete();

        return response()->json(['success' => true]);
    }
}
