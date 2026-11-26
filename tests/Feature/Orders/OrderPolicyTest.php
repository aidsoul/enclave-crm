<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected OrderPolicy $policy;

    protected Site $site;

    protected User $owner;

    protected User $manager1;

    protected User $manager2;

    protected User $courier1;

    protected User $courier2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new OrderPolicy;

        $this->site = Site::create([
            'name' => 'Policy Site',
            'domain' => 'policy.example.com',
            'token' => 'policy_token',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'system_name' => 'Owner',
            'telegram_user_id' => 20001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager1 = User::create([
            'system_name' => 'Manager 1',
            'telegram_user_id' => 20002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->manager2 = User::create([
            'system_name' => 'Manager 2',
            'telegram_user_id' => 20003,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->courier1 = User::create([
            'system_name' => 'Courier 1',
            'telegram_user_id' => 20004,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->courier2 = User::create([
            'system_name' => 'Courier 2',
            'telegram_user_id' => 20005,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);
    }

    protected function createOrder(int $managerId, ?int $courierId = null): Order
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $managerId,
            'total_price' => 1000,
            'purchase_price' => 700,
            'status' => OrderStatus::CREATED,
        ]);

        if ($courierId) {
            $order->delivery()->create([
                'type' => 'courier',
                'user_id' => $courierId,
                'delivery_address' => 'Тестовый адрес',
            ]);
        }

        return $order;
    }

    public function test_view_policy(): void
    {
        $order = $this->createOrder($this->manager1->id, $this->courier1->id);

        $this->assertTrue($this->policy->view($this->owner, $order));
        $this->assertTrue($this->policy->view($this->manager1, $order));
        $this->assertTrue($this->policy->view($this->manager2, $order)); // Managers can view
        $this->assertTrue($this->policy->view($this->courier1, $order)); // Assigned courier
        $this->assertFalse($this->policy->view($this->courier2, $order)); // Unassigned courier
    }

    public function test_update_policy(): void
    {
        $order = $this->createOrder($this->manager1->id, $this->courier1->id);

        $this->assertTrue($this->policy->update($this->owner, $order));
        $this->assertTrue($this->policy->update($this->manager1, $order)); // Assigned manager
        $this->assertFalse($this->policy->update($this->manager2, $order)); // Non-assigned manager
        $this->assertFalse($this->policy->update($this->courier1, $order)); // Courier cannot update
    }

    public function test_complete_policy(): void
    {
        $order = $this->createOrder($this->manager1->id, $this->courier1->id);

        $this->assertTrue($this->policy->complete($this->owner, $order));
        $this->assertTrue($this->policy->complete($this->manager1, $order));
        $this->assertFalse($this->policy->complete($this->manager2, $order));
        $this->assertTrue($this->policy->complete($this->courier1, $order));
        $this->assertFalse($this->policy->complete($this->courier2, $order));
    }

    public function test_cancel_policy(): void
    {
        $order = $this->createOrder($this->manager1->id, $this->courier1->id);

        $this->assertTrue($this->policy->cancel($this->owner, $order));
        $this->assertTrue($this->policy->cancel($this->manager1, $order));
        $this->assertFalse($this->policy->cancel($this->manager2, $order));
        $this->assertFalse($this->policy->cancel($this->courier1, $order));
    }

    public function test_change_status_policy_only_owner(): void
    {
        $this->assertTrue($this->policy->changeStatus($this->owner));
        $this->assertFalse($this->policy->changeStatus($this->manager1));
        $this->assertFalse($this->policy->changeStatus($this->courier1));
    }
}
