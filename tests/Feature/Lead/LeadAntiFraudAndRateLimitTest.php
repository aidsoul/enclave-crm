<?php

namespace Tests\Feature\Lead;

use App\Jobs\ProcessIncomingLeadJob;
use App\Models\Lead;
use App\Models\Site;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class LeadAntiFraudAndRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->site = Site::create([
            'name' => 'Test Store',
            'domain' => 'teststore.by',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);
    }

    public function test_incoming_leads_rate_limited_by_site_token(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $phone = '+37529'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT);
            $response = $this->withHeader('X-API-Token', $this->site->api_token)
                ->postJson('/api/leads/incoming', [
                    'type' => 'callback',
                    'client_name' => "User {$i}",
                    'client_phone' => $phone,
                    'client_ip' => '192.168.1.'.($i + 1),
                    'fingerprint' => "fp_token_test_{$i}",
                ]);

            $this->assertSame(202, $response->getStatusCode(), "Request {$i} failed with status {$response->getStatusCode()}");
        }

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'Over Limit User',
                'client_phone' => '+375299998877',
                'client_ip' => '192.168.2.1',
                'fingerprint' => 'fp_token_test_over',
            ]);

        $response->assertStatus(429);
    }

    public function test_incoming_leads_rate_limited_by_client_ip(): void
    {
        $fixedIp = '198.51.100.42';

        for ($i = 0; $i < 15; $i++) {
            $phone = '+37529'.str_pad((string) (2000000 + $i), 7, '0', STR_PAD_LEFT);
            $response = $this->withHeader('X-API-Token', $this->site->api_token)
                ->postJson('/api/leads/incoming', [
                    'type' => 'callback',
                    'client_name' => "User {$i}",
                    'client_phone' => $phone,
                    'client_ip' => $fixedIp,
                    'fingerprint' => "fp_ip_test_{$i}",
                ]);

            $this->assertSame(202, $response->getStatusCode(), "Request {$i} failed with status {$response->getStatusCode()}");
        }

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'IP Blocked User',
                'client_phone' => '+375292999999',
                'client_ip' => $fixedIp,
                'fingerprint' => 'fp_ip_test_over',
            ]);

        $response->assertStatus(429);
    }

    public function test_incoming_leads_rate_limited_by_device_fingerprint(): void
    {
        $fixedFingerprint = 'device_fingerprint_hash_abc123';

        for ($i = 0; $i < 10; $i++) {
            $phone = '+37529'.str_pad((string) (3000000 + $i), 7, '0', STR_PAD_LEFT);
            $response = $this->withHeader('X-API-Token', $this->site->api_token)
                ->postJson('/api/leads/incoming', [
                    'type' => 'callback',
                    'client_name' => "User {$i}",
                    'client_phone' => $phone,
                    'client_ip' => '10.0.0.'.($i + 1),
                    'fingerprint' => $fixedFingerprint,
                ]);

            $this->assertSame(202, $response->getStatusCode(), "Request {$i} failed with status {$response->getStatusCode()}");
        }

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'FP Blocked User',
                'client_phone' => '+375293999999',
                'client_ip' => '10.0.0.99',
                'fingerprint' => $fixedFingerprint,
            ]);

        $response->assertStatus(429);
    }

    public function test_incoming_leads_rate_limited_by_contact_phone(): void
    {
        $fixedPhone = '+375294567890';

        for ($i = 0; $i < 5; $i++) {
            $response = $this->withHeader('X-API-Token', $this->site->api_token)
                ->postJson('/api/leads/incoming', [
                    'type' => 'callback',
                    'client_name' => "User {$i}",
                    'client_phone' => $fixedPhone,
                    'client_ip' => '172.16.0.'.($i + 1),
                    'fingerprint' => "fp_contact_{$i}",
                    'external_id' => "ext_contact_{$i}",
                ]);

            $this->assertSame(202, $response->getStatusCode(), "Request {$i} failed with status {$response->getStatusCode()}");
        }

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'Contact Blocked User',
                'client_phone' => $fixedPhone,
                'client_ip' => '172.16.0.99',
                'fingerprint' => 'fp_contact_over',
                'external_id' => 'ext_contact_over',
            ]);

        $response->assertStatus(429);
    }

    public function test_antifraud_blocks_honeypot_fields(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'Spam Bot',
                'client_phone' => '+375295551234',
                '_honey' => 'http://spam-link.ru',
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'spam' => true,
                'message' => 'Lead rejected by anti-fraud protection',
            ]);

        Queue::assertNotPushed(ProcessIncomingLeadJob::class);
    }

    public function test_antifraud_blocks_disposable_email(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'contact',
                'client_name' => 'Temp Mail User',
                'client_email' => 'attacker123@mailinator.com',
                'message' => 'Test message',
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'spam' => true,
                'message' => 'Lead rejected by anti-fraud protection',
            ]);

        Queue::assertNotPushed(ProcessIncomingLeadJob::class);
    }

    public function test_antifraud_blocks_suspicious_phone_patterns(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'Dummy Phone User',
                'client_phone' => '+375291111111',
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'spam' => true,
                'message' => 'Lead rejected by anti-fraud protection',
            ]);

        Queue::assertNotPushed(ProcessIncomingLeadJob::class);
    }

    public function test_antifraud_blocks_rapid_phone_rotation(): void
    {
        Queue::fake();
        $deviceFp = 'fp_sms_bomber_target_999';

        $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'Victim 1',
                'client_phone' => '+375297112233',
                'fingerprint' => $deviceFp,
            ]);

        $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'Victim 2',
                'client_phone' => '+375297223344',
                'fingerprint' => $deviceFp,
            ]);

        $res3 = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'Victim 3',
                'client_phone' => '+375297334455',
                'fingerprint' => $deviceFp,
            ]);

        $res3->assertStatus(202)
            ->assertJson([
                'spam' => true,
                'message' => 'Lead rejected by anti-fraud protection',
            ]);
    }

    public function test_antifraud_blocks_bad_bot_user_agent(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->withHeader('User-Agent', 'python-requests/2.28.1')
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'Python Scraper',
                'client_phone' => '+375298887766',
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'spam' => true,
                'message' => 'Lead rejected by anti-fraud protection',
            ]);

        Queue::assertNotPushed(ProcessIncomingLeadJob::class);
    }

    public function test_antifraud_blocks_spam_urls_in_name(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'Buy Crypto https://scam-exchange.ru',
                'client_phone' => '+375296665544',
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'spam' => true,
                'message' => 'Lead rejected by anti-fraud protection',
            ]);

        Queue::assertNotPushed(ProcessIncomingLeadJob::class);
    }

    public function test_clean_lead_is_accepted_with_fingerprint_telemetry(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-API-Token', $this->site->api_token)
            ->postJson('/api/leads/incoming', [
                'type' => 'callback',
                'client_name' => 'Alexey Genuine',
                'client_phone' => '+375295432109',
                'client_ip' => '203.0.113.195',
                'fingerprint' => 'clean_visitor_device_123',
            ]);

        $response->assertStatus(202)
            ->assertJson(['status' => 'accepted']);

        Queue::assertPushed(ProcessIncomingLeadJob::class, function ($job) {
            $data = $job->validatedData;
            $antifraud = $data['extra_data']['_antifraud'] ?? [];

            return ($antifraud['fingerprint'] ?? null) === 'clean_visitor_device_123'
                && ($antifraud['client_ip'] ?? null) === '203.0.113.195'
                && ($antifraud['is_suspicious'] ?? true) === false
                && ($antifraud['fraud_score'] ?? 100) === 0;
        });
    }

    public function test_suspicious_lead_skips_manager_telegram_notification(): void
    {
        $mockTelegram = Mockery::mock(TelegramService::class);
        $mockTelegram->shouldNotReceive('notifyManagersAboutNewLead');
        $this->app->instance(TelegramService::class, $mockTelegram);

        $leadData = [
            'client_name' => 'Suspicious Alex',
            'client_phone' => '+375299876543',
            'extra_data' => [
                '_antifraud' => [
                    'is_suspicious' => true,
                    'fraud_score' => 60,
                ],
            ],
        ];

        $job = new ProcessIncomingLeadJob($this->site->id, $leadData);
        $this->app->call([$job, 'handle']);

        $lead = Lead::where('site_id', $this->site->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame('375299876543', $lead->phone);
        $this->assertSame('+375299876543', $lead->phone_with_plus);
        $this->assertTrue($lead->extra_data['_antifraud']['is_suspicious']);
    }
}
