<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\RoleService;

class OrderPolicy
{
    public function atWarehouse(User $user, Order $order): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        if ($user->isManager() && $order->manager_id === $user->id) {
            return true;
        }

        if ($user->isCourier() && $this->isCourierAssigned($user, $order)) {
            return true;
        }

        return false;
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isOwner() || $user->isManager()) {
            return true;
        }

        if ($user->isCourier()) {
            return $this->isCourierAssigned($user, $order);
        }

        return false;
    }

    public function chat(User $user, Order $order): bool
    {
        return app(RoleService::class)->canAccessOrderChat($user, $order);
    }

    public function update(User $user, Order $order): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        if ($user->isManager() && $order->manager_id === $user->id) {
            return true;
        }

        return false;
    }

    public function changeStatus(User $user): bool
    {
        return $user->isOwner();
    }

    public function complete(User $user, Order $order): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        if ($user->isManager() && $order->manager_id === $user->id) {
            return true;
        }

        if ($user->isCourier() && $this->isCourierAssigned($user, $order)) {
            return true;
        }

        return false;
    }

    public function uploadProof(User $user, Order $order): bool
    {
        if ($order->status === OrderStatus::CANCELLED) {
            return false;
        }

        if ($order->status === OrderStatus::COMPLETED) {
            return $user->isOwner();
        }

        if ($user->isOwner()) {
            return true;
        }

        if ($user->isCourier() && $this->isCourierAssigned($user, $order)) {
            return true;
        }

        return false;
    }

    public function deleteProof(User $user, Order $order): bool
    {
        if ($order->status === OrderStatus::CANCELLED) {
            return false;
        }

        if ($order->status === OrderStatus::COMPLETED) {
            return $user->isOwner();
        }

        return $this->uploadProof($user, $order);
    }

    public function uploadSignature(User $user, Order $order): bool
    {
        if ($order->status === OrderStatus::CANCELLED) {
            return false;
        }

        if ($order->status === OrderStatus::COMPLETED) {
            return $user->isOwner();
        }

        if ($user->isOwner()) {
            return true;
        }

        if ($user->isCourier() && $this->isCourierAssigned($user, $order)) {
            return true;
        }

        return false;
    }

    public function deleteSignature(User $user, Order $order): bool
    {
        if ($order->status === OrderStatus::CANCELLED) {
            return false;
        }

        return $user->isOwner();
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        if ($user->isManager() && $order->manager_id === $user->id) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isOwner();
    }

    private function isCourierAssigned(User $user, Order $order): bool
    {
        if ($order->relationLoaded('delivery')) {
            return $order->delivery->contains(fn ($delivery) => (int) $delivery->user_id === (int) $user->id);
        }

        return $order->delivery()->where('user_id', $user->id)->exists();
    }
}
