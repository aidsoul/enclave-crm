<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TmaController extends Controller
{
    public function index(Request $request, ?string $role = null)
    {
        $user = auth()->user();

        if (! $user) {
            if ($inviteToken = $request->query('invite')) {
                $invite = Invitation::where('token', $inviteToken)->where('is_active', true)->first();
                if ($invite) {
                    $role = $invite->role->value;
                }
            }

            $botUsername = (string) (config('services.telegram.bot_username') ?: config('nutgram.name', 'enclave_crmbot'));

            return response()->view('tma.auth.entry', [
                'role' => $role ?? 'owner',
                'botUsername' => ltrim($botUsername, '@'),
            ]);
        }

        // When visiting the root '/' or '/tma', automatically resolve to the authenticated user's actual role
        if (! $role || $request->path() === '/' || $request->path() === 'tma') {
            $role = $user->role->value;
        }

        if ($role !== $user->role->value) {
            $allowedRoles = match ($user->role) {
                UserRole::OWNER => ['owner', 'manager', 'courier'],
                UserRole::MANAGER => ['manager'],
                UserRole::COURIER => ['courier'],
            };

            if (! in_array($role, $allowedRoles, true)) {
                return response()->view('tma.blocked', [
                    'message' => 'Доступ к данному разделу ограничен для вашей роли.',
                ], 403);
            }
        }

        return match ($role) {
            'owner' => $this->ownerDashboard($request, $user),
            'manager' => $this->managerOrders($request, $user),
            'courier' => $this->courierIndex($request, $user),
            default => response()->json(['error' => 'Not found'], 404),
        };
    }

    protected function ownerDashboard(Request $request, $user)
    {
        return response()->view('tma.owner.dashboard', [
            'user' => $user,
        ]);
    }

    protected function managerOrders(Request $request, $user)
    {
        return response()->view('tma.manager.orders', [
            'user' => $user,
        ]);
    }

    protected function courierIndex(Request $request, $user)
    {
        return response()->view('tma.courier.index', [
            'user' => $user,
        ]);
    }

    public function personnel(Request $request)
    {
        $user = auth()->user();

        if (! $user || $user->role !== UserRole::OWNER) {
            return response()->view('tma.blocked', ['message' => 'Раздел «Сотрудники» доступен только владельцу системы.'], 403);
        }

        return response()->view('tma.owner.personnel', [
            'user' => $user,
        ]);
    }

    public function audit(Request $request)
    {
        $user = auth()->user();

        if (! $user || $user->role !== UserRole::OWNER) {
            return response()->view('tma.blocked', ['message' => 'Раздел «Журнал аудита» доступен только владельцу системы.'], 403);
        }

        return response()->view('tma.owner.audit', [
            'user' => $user,
        ]);
    }

    public function auditShow(Request $request, int $id)
    {
        $user = auth()->user();

        if (! $user || $user->role !== UserRole::OWNER) {
            return response()->view('tma.blocked', ['message' => 'Раздел «Журнал аудита» доступен только владельцу системы.'], 403);
        }

        return response()->view('tma.owner.audit-show', [
            'user' => $user,
        ]);
    }

    public function settings(Request $request)
    {
        $user = auth()->user();

        if (! $user || ! in_array($user->role, [UserRole::OWNER, UserRole::MANAGER, UserRole::COURIER], true)) {
            return response()->view('tma.blocked', ['message' => 'Раздел «Настройки» недоступен для вашего аккаунта.'], 403);
        }

        return response()->view('tma.owner.settings', [
            'user' => $user,
        ]);
    }

    public function financial(Request $request)
    {
        $user = auth()->user();

        if (! $user || $user->role !== UserRole::OWNER) {
            return response()->view('tma.blocked', ['message' => 'Раздел «Финансы» доступен только владельцу системы.'], 403);
        }

        $metric = $request->query('metric', 'total_revenue');

        return response()->view('tma.owner.financial', [
            'user' => $user,
            'metric' => $metric,
        ]);
    }

    public function analytics(Request $request)
    {
        $user = auth()->user();

        if (! $user || $user->role !== UserRole::OWNER) {
            return response()->view('tma.blocked', ['message' => 'Раздел «Аналитика» доступен только владельцу системы.'], 403);
        }

        return response()->view('tma.owner.analytics', [
            'user' => $user,
        ]);
    }

    public function orderList(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->view('tma.blocked', ['message' => 'Доступ к заказам доступен только авторизованным сотрудникам.'], 403);
        }

        return response()->view('tma.order-list', [
            'user' => $user,
        ]);
    }

    public function orderCreate(Request $request)
    {
        $user = auth()->user();

        if (! $user || ($user->role !== UserRole::OWNER && $user->role !== UserRole::MANAGER)) {
            return response()->view('tma.blocked', ['message' => 'Создание заказов доступно только владельцу и менеджеру.'], 403);
        }

        return response()->view('tma.manager.order-create', [
            'user' => $user,
        ]);
    }

    public function orderEdit(Request $request, int $id)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->view('tma.blocked', ['message' => 'Доступ запрещён.'], 403);
        }

        $order = Order::with(['site', 'manager', 'customer', 'delivery.courier', 'payment', 'items', 'discounts'])->find($id);

        if (! $order) {
            abort(404);
        }

        if (! Gate::allows('update', $order)) {
            return response()->view('tma.blocked', ['message' => 'Редактирование данного заказа недоступно для вашей роли.'], 403);
        }

        return response()->view('tma.manager.order-edit', [
            'user' => $user,
            'orderId' => $id,
        ]);
    }

    public function orderShow(Request $request, int $id)
    {
        $user = auth()->user();
        $order = Order::with(['site', 'manager', 'customer', 'delivery.courier', 'payment', 'items', 'discounts'])->find($id);

        if (! $order) {
            abort(404);
        }

        if (! $user || ! Gate::allows('view', $order)) {
            return response()->view('tma.blocked', ['message' => 'Просмотр данного заказа недоступен для вашей роли.'], 403);
        }

        $canEdit = Gate::allows('update', $order);

        $hasActiveOrder = false;

        return response()->view('tma.manager.order-show', [
            'user' => $user,
            'order' => $order,
            'orderId' => $id,
            'canEdit' => $canEdit,
            'hasActiveOrder' => $hasActiveOrder,
        ]);
    }

    public function orderChat(Request $request, int $id)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->view('tma.blocked', ['message' => 'Доступ запрещён.'], 403);
        }

        $order = Order::with(['manager', 'customer', 'delivery.courier'])->find($id);

        if (! $order) {
            abort(404);
        }

        if (! Gate::allows('chat', $order)) {
            return response()->view('tma.blocked', ['message' => 'Чат по данному заказу недоступен для вашей роли.'], 403);
        }

        return response()->view('tma.orders.chat', [
            'user' => $user,
            'order' => $order,
            'orderId' => $id,
        ]);
    }

    public function customers(Request $request)
    {
        $user = auth()->user();

        if (! $user || ($user->role !== UserRole::OWNER && $user->role !== UserRole::MANAGER)) {
            return response()->view('tma.blocked', ['message' => 'Раздел «Клиенты» доступен только владельцу и менеджеру.'], 403);
        }

        return response()->view('tma.customers.index', [
            'user' => $user,
        ]);
    }

    public function customerShow(Request $request, int $id)
    {
        $user = auth()->user();

        if (! $user || ($user->role !== UserRole::OWNER && $user->role !== UserRole::MANAGER)) {
            return response()->view('tma.blocked', ['message' => 'Раздел «Клиенты» доступен только владельцу и менеджеру.'], 403);
        }

        return response()->view('tma.customers.show', [
            'user' => $user,
            'customerId' => $id,
        ]);
    }

    public function leads(Request $request)
    {
        $user = auth()->user();

        if (! $user || ($user->role !== UserRole::OWNER && $user->role !== UserRole::MANAGER)) {
            return response()->view('tma.blocked', ['message' => 'Раздел «Лиды» доступен только владельцу и менеджеру.'], 403);
        }

        return response()->view('tma.leads.index', [
            'user' => $user,
        ]);
    }

    public function sites(Request $request)
    {
        $user = auth()->user();

        if (! $user || $user->role !== UserRole::OWNER) {
            return response()->view('tma.blocked', ['message' => 'Раздел «Сайты» доступен только владельцу системы.'], 403);
        }

        return response()->view('tma.owner.sites', [
            'user' => $user,
        ]);
    }
}
