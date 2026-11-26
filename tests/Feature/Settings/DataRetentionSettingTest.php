<?php

namespace Tests\Feature\Settings;

use App\Enums\SettingKey;
use App\Enums\UserRole;
use App\Jobs\DataRetentionJob;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\DataRetentionService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataRetentionSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Admin',
            'telegram_user_id' => 99001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Test Site',
            'domain' => 'example.com',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);
    }

    public function test_retention_days_defaults_to_zero(): void
    {
        $this->assertSame(0, SettingKey::RETENTION_DAYS->default());

        $settingService = app(SettingService::class);
        $this->assertSame(0, $settingService->get(SettingKey::RETENTION_DAYS, null));
    }

    public function test_owner_can_set_retention_days_to_zero(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/settings/retention', [
                'retention_days' => 0,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'retention_days' => 0,
            ]);

        $settingService = app(SettingService::class);
        $this->assertSame(0, $settingService->get(SettingKey::RETENTION_DAYS, null));
    }

    public function test_invalid_retention_days_rejected(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/settings/retention', [
                'retention_days' => 999,
            ]);

        $response->assertStatus(422);
    }

    public function test_data_retention_job_does_not_delete_orders_when_retention_is_zero(): void
    {
        $settingService = app(SettingService::class);
        $settingService->set(SettingKey::RETENTION_DAYS, 0, 'global');

        $order = Order::create([
            'site_id' => $this->site->id,
            'order_number' => 'ORD-OLD-001',
            'customer_name' => 'Old Customer',
            'customer_phone' => '+1234567890',
            'total_price' => 1000,
            'purchase_price' => 500,
            'created_at' => now()->subDays(120),
        ]);

        $job = new DataRetentionJob;
        $job->handle($settingService, app(DataRetentionService::class));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
        ]);
    }
}
