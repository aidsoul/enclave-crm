<?php

namespace Tests\Feature\Lead;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_list_leads_with_eager_loaded_relations(): void
    {
        $site = Site::create([
            'name' => 'Online Shop',
            'domain' => 'shop.test',
            'token' => 'token-test-123456789012345',
            'is_active' => true,
        ]);

        $manager = User::create([
            'system_name' => 'Manager Kate',
            'telegram_user_id' => 999111,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        Lead::create([
            'site_id' => $site->id,
            'manager_id' => $manager->id,
            'type' => LeadType::CALLBACK,
            'status' => LeadStatus::NEW,
            'name' => 'John Doe',
            'phone' => '+375291234567',
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/api/leads?page=1');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'phone',
                        'site',
                        'manager' => [
                            'id',
                            'system_name',
                        ],
                    ],
                ],
            ]);
    }

    public function test_convert_to_order_idempotent_and_prevents_duplicate_orders(): void
    {
        $site = Site::create([
            'name' => 'Online Shop',
            'domain' => 'shop.test',
            'token' => 'token-test-123456789012345',
            'is_active' => true,
        ]);

        $manager = User::create([
            'system_name' => 'Manager Kate',
            'telegram_user_id' => 999111,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $lead = Lead::create([
            'site_id' => $site->id,
            'type' => LeadType::CALLBACK,
            'status' => LeadStatus::NEW,
            'name' => 'John Doe',
            'phone' => '+375291234567',
        ]);

        $firstResponse = $this->actingAs($manager)
            ->postJson("/api/leads/{$lead->id}/convert-to-order");

        $firstResponse->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $orderId = $firstResponse->json('order_id');
        $this->assertNotNull($orderId);

        // Second attempt on already converted lead
        $secondResponse = $this->actingAs($manager)
            ->postJson("/api/leads/{$lead->id}/convert-to-order");

        $secondResponse->assertOk()
            ->assertJson([
                'success' => true,
                'order_id' => $orderId,
                'message' => 'Заказ уже был создан ранее',
            ]);

        $this->assertEquals(1, Order::count());
    }

    public function test_incoming_lead_deduplicates_by_email_when_phone_is_absent(): void
    {
        $site = Site::create([
            'name' => 'Online Shop',
            'domain' => 'shop.test',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $payload = [
            'type' => 'question',
            'client_name' => 'Anna',
            'client_email' => 'anna@example.com',
            'message' => 'First message',
        ];

        $res1 = $this->withHeader('X-API-Token', $site->api_token)
            ->postJson('/api/leads/incoming', $payload);

        $res1->assertStatus(202)
            ->assertJson(['status' => 'accepted']);

        // Immediate duplicate request with same email and type
        $res2 = $this->withHeader('X-API-Token', $site->api_token)
            ->postJson('/api/leads/incoming', $payload);

        $res2->assertStatus(202)
            ->assertJson([
                'duplicate' => true,
                'message' => 'Duplicate lead ignored',
            ]);
    }

    public function test_lead_lifecycle_is_recorded_in_audit_logs(): void
    {
        $site = Site::create([
            'name' => 'Online Shop',
            'domain' => 'shop.test',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $manager = User::create([
            'system_name' => 'Manager Kate',
            'telegram_user_id' => 999111,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $owner = User::create([
            'system_name' => 'Owner Ivan',
            'telegram_user_id' => 999222,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        // Create lead
        $lead = Lead::create([
            'site_id' => $site->id,
            'type' => LeadType::CALLBACK,
            'status' => LeadStatus::NEW,
            'name' => 'John Doe',
            'phone' => '+375291234567',
        ]);

        $this->assertDatabaseHas('action_logs', [
            'lead_id' => $lead->id,
            'action_type' => 'create',
            'field_name' => 'lead',
        ]);

        // Update lead status
        $this->actingAs($manager)
            ->patchJson("/api/leads/{$lead->id}", [
                'status' => 'in_progress',
            ])
            ->assertOk();

        $this->assertDatabaseHas('action_logs', [
            'lead_id' => $lead->id,
            'action_type' => 'status_change',
            'field_name' => 'status',
            'old_value' => 'Новая',
            'new_value' => 'В работе',
        ]);

        // Delete lead
        $this->actingAs($manager)
            ->deleteJson("/api/leads/{$lead->id}")
            ->assertOk();

        $this->assertDatabaseHas('action_logs', [
            'action_type' => 'delete',
            'field_name' => 'lead',
        ]);

        // Verify owner sees lead actions in audit API
        $res = $this->actingAs($owner)
            ->getJson('/api/audit/logs');

        $res->assertOk();
        $logs = collect($res->json('logs'));
        $leadLogs = $logs->where('field_name', 'lead');
        $this->assertNotEmpty($leadLogs);
    }
}
