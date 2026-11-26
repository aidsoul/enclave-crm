<?php

namespace Tests\Unit\Orders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\Orders\ActionLogger;
use App\Services\Orders\OrderStatusMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderStatusMachineTest extends TestCase
{
    use RefreshDatabase;

    protected OrderStatusMachine $statusMachine;

    protected Site $site;

    protected User $owner;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statusMachine = new OrderStatusMachine(new ActionLogger);

        $this->site = Site::create([
            'name' => 'Test Site',
            'domain' => 'orders.example.com',
            'token' => 'token_xyz',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'system_name' => 'Owner',
            'telegram_user_id' => 10001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager',
            'telegram_user_id' => 10002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);
    }

    public function test_owner_can_change_status_and_triggers_event_and_log(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'total_price' => 500,
            'purchase_price' => 300,
            'status' => OrderStatus::CREATED,
        ]);

        $updated = $this->statusMachine->changeStatus($order->id, OrderStatus::ACCEPTED, $this->owner->id);

        $this->assertEquals(OrderStatus::ACCEPTED, $updated->status);
        Event::assertDispatched(OrderStatusChanged::class);

        $this->assertDatabaseHas('action_logs', [
            'order_id' => $order->id,
            'actor_id' => $this->owner->id,
            'field_name' => 'status',
            'old_value' => OrderStatus::CREATED->value,
            'new_value' => OrderStatus::ACCEPTED->value,
        ]);
    }

    public function test_manager_can_mark_at_warehouse_from_allowed_status(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 1000,
            'purchase_price' => 700,
            'status' => OrderStatus::ACCEPTED,
        ]);

        $updated = $this->statusMachine->markAtWarehouse($order->id, $this->manager->id);

        $this->assertEquals(OrderStatus::AT_WAREHOUSE, $updated->status);
        Event::assertDispatched(OrderStatusChanged::class);
    }

    public function test_manager_cannot_transition_to_invalid_status(): void
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 1000,
            'purchase_price' => 700,
            'status' => OrderStatus::COMPLETED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Некорректный переход статуса');

        $this->statusMachine->markCancelled($order->id, $this->manager->id);
    }

    public function test_mark_complete_transitions_successfully(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 1500,
            'purchase_price' => 1000,
            'status' => OrderStatus::ASSIGNED,
        ]);

        $updated = $this->statusMachine->markComplete($order->id, $this->manager->id);

        $this->assertEquals(OrderStatus::COMPLETED, $updated->status);
    }

    public function test_mark_cancelled_transitions_successfully(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 1500,
            'purchase_price' => 1000,
            'status' => OrderStatus::CREATED,
        ]);

        $updated = $this->statusMachine->markCancelled($order->id, $this->manager->id);

        $this->assertEquals(OrderStatus::CANCELLED, $updated->status);
    }

    public function test_manager_cannot_cancel_order_if_not_assigned_manager(): void
    {
        $otherManager = User::create([
            'system_name' => 'Other Manager',
            'telegram_user_id' => 99999,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 1500,
            'purchase_price' => 1000,
            'status' => OrderStatus::CREATED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Менеджер может отменять только свои заказы');

        $this->statusMachine->markCancelled($order->id, $otherManager->id);
    }
}
