<?php

namespace Tests\Feature\Orders;

use App\Jobs\ProcessIncomingOrderJob;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class IncomingOrdersThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->site = Site::create([
            'name' => 'Store 1',
            'domain' => 'store1.com',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);
    }

    public function test_incoming_orders_endpoint_has_throttle_protection(): void
    {
        $payload = [
            'client_name' => 'Test Customer',
            'client_phone' => '+375291234567',
            'delivery_address' => 'Test Address 1',
            'order_items' => [
                [
                    'name' => 'Product 1',
                    'price' => 100,
                    'qty' => 1,
                ],
            ],
        ];

        for ($i = 0; $i < 60; $i++) {
            $response = $this->withHeader('X-API-Token', $this->site->api_token)
                ->postJson('/api/orders/incoming', $payload);

            $this->assertSame(202, $response->getStatusCode(), "Request {$i} failed with status {$response->getStatusCode()}");
        }

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', $payload);

        $response->assertStatus(429);
    }

    public function test_incoming_orders_rate_limited_by_client_ip(): void
    {
        $fixedIp = '198.51.100.88';

        for ($i = 0; $i < 15; $i++) {
            $response = $this->withHeader('X-API-Token', $this->site->api_token)
                ->postJson('/api/orders/incoming', [
                    'client_name' => "Buyer {$i}",
                    'client_phone' => '+37529'.str_pad((string) (5000000 + $i), 7, '0', STR_PAD_LEFT),
                    'client_ip' => $fixedIp,
                    'order_items' => [
                        ['name' => 'Item', 'price' => 50, 'qty' => 1],
                    ],
                ]);

            $this->assertSame(202, $response->getStatusCode(), "Request {$i} failed with status {$response->getStatusCode()}");
        }

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'client_name' => 'Over IP Buyer',
                'client_phone' => '+375295999999',
                'client_ip' => $fixedIp,
                'order_items' => [
                    ['name' => 'Item', 'price' => 50, 'qty' => 1],
                ],
            ]);

        $response->assertStatus(429);
    }

    public function test_incoming_orders_rate_limited_by_fingerprint(): void
    {
        $fixedFp = 'order_fp_hash_device_xyz';

        for ($i = 0; $i < 10; $i++) {
            $response = $this->withHeader('X-API-Token', $this->site->api_token)
                ->postJson('/api/orders/incoming', [
                    'client_name' => "Buyer {$i}",
                    'client_phone' => '+37529'.str_pad((string) (6000000 + $i), 7, '0', STR_PAD_LEFT),
                    'fingerprint' => $fixedFp,
                    'order_items' => [
                        ['name' => 'Item', 'price' => 50, 'qty' => 1],
                    ],
                ]);

            $this->assertSame(202, $response->getStatusCode(), "Request {$i} failed with status {$response->getStatusCode()}");
        }

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'client_name' => 'Over FP Buyer',
                'client_phone' => '+375296999999',
                'fingerprint' => $fixedFp,
                'order_items' => [
                    ['name' => 'Item', 'price' => 50, 'qty' => 1],
                ],
            ]);

        $response->assertStatus(429);
    }

    public function test_antifraud_blocks_honeypot_on_incoming_orders(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'client_name' => 'Bot Order',
                'client_phone' => '+375297123456',
                '_honey' => 'spam_bot_input',
                'order_items' => [
                    ['name' => 'Product', 'price' => 100, 'qty' => 1],
                ],
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'spam' => true,
                'message' => 'Order rejected by anti-fraud protection',
            ]);

        Queue::assertNotPushed(ProcessIncomingOrderJob::class);
    }

    public function test_antifraud_blocks_disposable_email_on_incoming_orders(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'client_name' => 'Fake Buyer',
                'client_phone' => '+375297234567',
                'client_email' => 'fake_order@mailinator.com',
                'order_items' => [
                    ['name' => 'Product', 'price' => 100, 'qty' => 1],
                ],
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'spam' => true,
                'message' => 'Order rejected by anti-fraud protection',
            ]);

        Queue::assertNotPushed(ProcessIncomingOrderJob::class);
    }

    public function test_antifraud_blocks_suspicious_phone_on_incoming_orders(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'client_name' => 'Fake Phone Buyer',
                'client_phone' => '+375291111111',
                'order_items' => [
                    ['name' => 'Product', 'price' => 100, 'qty' => 1],
                ],
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'spam' => true,
                'message' => 'Order rejected by anti-fraud protection',
            ]);

        Queue::assertNotPushed(ProcessIncomingOrderJob::class);
    }

    public function test_clean_order_has_antifraud_telemetry(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/orders/incoming', [
                'client_name' => 'Genuine Customer',
                'client_phone' => '+375297345678',
                'client_ip' => '198.51.100.99',
                'fingerprint' => 'clean_order_device_fp',
                'order_items' => [
                    ['name' => 'Product Genuine', 'price' => 100, 'qty' => 1],
                ],
            ]);

        $response->assertStatus(202);

        Queue::assertPushed(ProcessIncomingOrderJob::class, function ($job) {
            $data = $job->validatedData;
            $antifraud = $data['extra_data']['_antifraud'] ?? [];

            return ($antifraud['fingerprint'] ?? null) === 'clean_order_device_fp'
                && ($antifraud['client_ip'] ?? null) === '198.51.100.99'
                && ($antifraud['fraud_score'] ?? 100) === 0;
        });
    }

    public function test_suspicious_order_skips_notification(): void
    {
        Queue::fake();

        $job = new ProcessIncomingOrderJob($this->site->id, [
            'client_name' => 'Suspicious Order',
            'client_phone' => '+375297456789',
            'order_items' => [
                ['name' => 'Product Suspicious', 'price' => 100, 'qty' => 1],
            ],
            'extra_data' => [
                '_antifraud' => [
                    'is_suspicious' => true,
                    'fraud_score' => 60,
                ],
            ],
        ]);

        $this->app->call([$job, 'handle']);

        Queue::assertNotPushed(SendOrderNotificationJob::class);
    }
}
