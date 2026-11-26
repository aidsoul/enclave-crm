<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

class RoleService
{
    protected array $orderAccessCache = [];

    public function getAllActive(): Collection
    {
        return User::active()->get();
    }

    public function getByRole(UserRole $role): Collection
    {
        return User::byRole($role)->active()->get();
    }

    public function getOwner(): ?User
    {
        return User::byRole(UserRole::OWNER)->active()->first();
    }

    public function getCouriers(): Collection
    {
        return $this->getByRole(UserRole::COURIER);
    }

    public function getManagers(): Collection
    {
        return $this->getByRole(UserRole::MANAGER);
    }

    public function clearOrderAccessCache(?int $orderId = null): void
    {
        if ($orderId === null) {
            $this->orderAccessCache = [];
        } else {
            foreach (array_keys($this->orderAccessCache) as $key) {
                if (str_ends_with($key, ":{$orderId}")) {
                    unset($this->orderAccessCache[$key]);
                }
            }
        }
    }

    public function canAccessOrder(User $user, int $orderId): bool
    {
        if ($user->isOwner() || $user->isManager()) {
            return true;
        }

        if ($user->isCourier()) {
            $key = "{$user->id}:{$orderId}";
            if (isset($this->orderAccessCache[$key])) {
                return $this->orderAccessCache[$key];
            }

            return $this->orderAccessCache[$key] = Delivery::where('order_id', $orderId)
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }

    public function canAccessOrderChat(User $user, int|Order $order): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        $orderId = $order instanceof Order ? $order->id : (int) $order;
        $key = "chat:{$user->id}:{$orderId}";
        if (isset($this->orderAccessCache[$key])) {
            return $this->orderAccessCache[$key];
        }

        if ($user->isManager()) {
            $managerId = $order instanceof Order
                ? $order->manager_id
                : Order::where('id', $orderId)->value('manager_id');

            return $this->orderAccessCache[$key] = ($managerId !== null && (int) $managerId === (int) $user->id);
        }

        if ($user->isCourier()) {
            if ($order instanceof Order) {
                $isAssigned = ($order->courier_id !== null && (int) $order->courier_id === (int) $user->id)
                    || ($order->relationLoaded('delivery')
                        ? $order->delivery->contains(fn ($d) => (int) $d->user_id === (int) $user->id)
                        : $order->delivery()->where('user_id', $user->id)->exists());

                return $this->orderAccessCache[$key] = $isAssigned;
            }

            return $this->orderAccessCache[$key] = Order::where('id', $orderId)
                ->forCourier($user->id)
                ->exists();
        }

        return false;
    }

    public function canViewAllOrders(User $user): bool
    {
        return $user->isOwner();
    }

    public function canManageUsers(User $user): bool
    {
        return $user->isOwner();
    }

    public function canChangeOrderStatus(User $user): bool
    {
        return $user->isOwner();
    }

    public function canCancelOrder(User $user, Order $order): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        if ($user->isManager() && $order->manager_id === $user->id) {
            return true;
        }

        return false;
    }

    public function canCompleteOrder(User $user, Order $order): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        if ($user->isManager() && $order->manager_id === $user->id) {
            return true;
        }

        return false;
    }

    public function canEditOrder(User $user, Order $order): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        if ($user->isManager() && $order->manager_id === $user->id) {
            return true;
        }

        return false;
    }
}
