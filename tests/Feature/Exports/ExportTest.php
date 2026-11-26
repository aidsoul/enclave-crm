<?php

namespace Tests\Feature\Exports;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Ivan',
            'telegram_user_id' => 13001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Store Export',
            'domain' => 'export.example.com',
            'is_active' => true,
        ]);
    }

    public function test_exports_financial_stats_to_csv(): void
    {
        Order::create([
            'site_id' => $this->site->id,
            'total_price' => 200.00,
            'purchase_price' => 110.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $response = $this->actingAs($this->owner)
            ->get('/api/exports/financial');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Выручка', $response->streamedContent());
        $this->assertStringContainsString('Чистая прибыль', $response->streamedContent());
    }

    public function test_sanitizes_csv_formula_injection(): void
    {
        $maliciousSite = Site::create([
            'name' => 'Malicious',
            'domain' => '=cmd|/c calc!A0',
            'is_active' => true,
        ]);

        Order::create([
            'site_id' => $maliciousSite->id,
            'total_price' => 150.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $response = $this->actingAs($this->owner)
            ->get('/api/exports/financial');

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringNotContainsString(';=cmd', $content);
        $this->assertStringContainsString("'=cmd", $content);
    }
}
