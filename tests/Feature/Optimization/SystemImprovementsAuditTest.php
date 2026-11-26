<?php

namespace Tests\Feature\Optimization;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Events\OrderCreated;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SystemImprovementsAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected User $courier;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Admin',
            'telegram_user_id' => 90001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Alex',
            'telegram_user_id' => 90002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier Sam',
            'telegram_user_id' => 90003,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Store Beta',
            'domain' => 'beta.store.test',
            'is_active' => true,
            'token' => 'beta-site-token',
        ]);
    }

    public function test_bulk_orders_rejects_more_than_100_ids(): void
    {
        $overloadedIds = range(1, 101);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/orders/bulk-accept', [
                'order_ids' => $overloadedIds,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_ids']);
    }

    public function test_bulk_assign_courier_rejects_more_than_100_ids(): void
    {
        $overloadedIds = range(1, 101);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/orders/bulk-assign', [
                'order_ids' => $overloadedIds,
                'delivery_courier_id' => $this->courier->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_ids']);
    }

    public function test_convert_lead_to_order_dispatches_order_created_event(): void
    {
        Event::fake([OrderCreated::class]);

        $lead = Lead::create([
            'site_id' => $this->site->id,
            'type' => 'callback',
            'status' => LeadStatus::NEW,
            'name' => 'Anna Lead',
            'phone' => '+375298889900',
        ]);

        $leadService = app(LeadService::class);
        $result = $leadService->convertToOrder($lead, $this->manager->id);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['order_id']);

        Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) use ($result) {
            return (int) $event->order->id === (int) $result['order_id'];
        });
    }

    public function test_leads_api_uses_lead_resource_with_proper_structure(): void
    {
        $lead = Lead::create([
            'site_id' => $this->site->id,
            'type' => 'callback',
            'status' => LeadStatus::NEW,
            'name' => 'Resource Test',
            'phone' => '+375291112233',
            'email' => 'resource@example.com',
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/leads');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'phone',
                        'email',
                        'type',
                        'type_label',
                        'status',
                        'status_label',
                        'status_color',
                    ],
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);

        $singleResponse = $this->actingAs($this->manager)
            ->getJson("/api/leads/{$lead->id}");

        $singleResponse->assertOk()
            ->assertJson([
                'id' => $lead->id,
                'name' => 'Resource Test',
                'email' => 'resource@example.com',
            ]);
    }

    public function test_financial_export_is_protected_by_rate_limiter(): void
    {
        $uniqueOwner = User::create([
            'system_name' => 'Owner Export Test',
            'telegram_user_id' => 999999,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        for ($i = 0; $i < 10; $i++) {
            $res = $this->actingAs($uniqueOwner)->getJson('/api/exports/financial');
            $this->assertEquals(200, $res->getStatusCode(), "Request {$i} was unexpectedly rate limited");
        }

        $blockedRes = $this->actingAs($uniqueOwner)->getJson('/api/exports/financial');
        $blockedRes->assertStatus(429);
    }
}
