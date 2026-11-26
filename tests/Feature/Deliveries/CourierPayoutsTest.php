<?php

namespace Tests\Feature\Deliveries;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierPayoutsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Ivan',
            'telegram_user_id' => 15001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Store Deliveries',
            'domain' => 'deliv.example.com',
            'is_active' => true,
        ]);
    }

    public function test_dashboard_stats_computes_courier_payouts_and_net_profit(): void
    {
        $order1 = Order::create([
            'site_id' => $this->site->id,
            'total_price' => 200.00,
            'purchase_price' => 100.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $order1->delivery()->create([
            'type' => 'courier',
            'delivery_cost' => 20.00,
        ]);

        $order2 = Order::create([
            'site_id' => $this->site->id,
            'total_price' => 150.00,
            'purchase_price' => 80.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $order2->delivery()->create([
            'type' => 'courier',
            'delivery_cost' => 15.00,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/dashboard/stats?period=today');

        $response->assertOk()
            ->assertJsonPath('stats.total_revenue', 350)
            ->assertJsonPath('stats.total_purchase', 180)
            ->assertJsonPath('stats.courier_payouts', 35)
            ->assertJsonPath('stats.net_profit', 135); // 350 - 180 - 35 = 135
    }
}
