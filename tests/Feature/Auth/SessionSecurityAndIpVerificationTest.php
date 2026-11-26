<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\ActionLog;
use App\Models\User;
use App\Services\Security\GeoIpService;
use App\Services\Security\SessionSecurityService;
use App\Services\Telegram\TelegramInitDataValidator;
use App\Services\UserSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

class SessionSecurityAndIpVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Redis::flushdb();
        Mockery::close();
        parent::tearDown();
    }

    protected function createSecurityUser(int $telegramId = 987654321): User
    {
        return User::create([
            'system_name' => 'Security Admin '.$telegramId,
            'telegram_user_id' => $telegramId,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);
    }

    public function test_geoip_service_resolves_headers_and_country(): void
    {
        $geoIpService = app(GeoIpService::class);

        // Test loopback IP
        $this->assertTrue($geoIpService->isPrivateOrReservedIp('127.0.0.1'));
        $this->assertTrue($geoIpService->isPrivateOrReservedIp('192.168.1.100'));
        $this->assertTrue($geoIpService->isPrivateOrReservedIp('10.0.0.5'));
        $this->assertFalse($geoIpService->isPrivateOrReservedIp('8.8.8.8'));

        // Test CF-Connecting-IP resolution
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_CF_CONNECTING_IP' => '93.125.1.42',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $this->assertEquals('93.125.1.42', $geoIpService->resolveClientIp($request));

        // Test CF-IPCountry header resolution
        $requestWithCountry = Request::create('/test', 'GET', [], [], [], [
            'HTTP_CF_CONNECTING_IP' => '93.125.1.42',
            'HTTP_CF_IPCOUNTRY' => 'BY',
        ]);
        $country = $geoIpService->resolveCountry('93.125.1.42', $requestWithCountry);
        $this->assertEquals('BY', $country['code']);
        $this->assertStringContainsString('Беларусь', $country['name']);
        $this->assertStringContainsString('🇧🇾', $country['name']);
    }

    public function test_auth_tma_registers_session_with_ip_and_country(): void
    {
        $user = $this->createSecurityUser(111222333);

        $mockValidator = Mockery::mock(TelegramInitDataValidator::class);
        $mockValidator->shouldReceive('getTelegramUserId')
            ->with('valid_init_data')
            ->andReturn('111222333');

        $this->app->instance(TelegramInitDataValidator::class, $mockValidator);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.10',
            'HTTP_CF_IPCOUNTRY' => 'RU',
        ])->postJson('/api/auth/tma', [
            'initData' => 'valid_init_data',
        ]);

        $response->assertStatus(200);
        $token = $response->json('session_token');
        $this->assertNotEmpty($token);

        $sessionData = json_decode(Redis::get("session:{$token}"), true);
        $this->assertEquals('198.51.100.10', $sessionData['ip']);
        $this->assertStringContainsString('Россия', $sessionData['country']);
        $this->assertContains('198.51.100.10', $sessionData['verified_ips']);
    }

    public function test_request_from_same_ip_is_allowed(): void
    {
        $user = $this->createSecurityUser(222333444);
        $token = Str::random(64);
        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($user, $token, (int) $user->telegram_user_id, 3600, '198.51.100.10', 'Беларусь 🇧🇾 (BY)');

        // Request from identical verified IP succeeds
        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $response->assertStatus(200);
        $response->assertJson(['id' => $user->id]);
    }

    public function test_request_from_unverified_ip_triggers_verification_and_alert(): void
    {
        $user = $this->createSecurityUser(333444555);
        config(['nutgram.token' => '123456789:ABCdefGhIjklMNopqrSTUvwxyz']);
        $token = Str::random(64);
        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($user, $token, (int) $user->telegram_user_id, 3600, '198.51.100.10', 'Беларусь 🇧🇾 (BY)');

        // Mock Nutgram to verify bot alert is sent
        $mockBot = Mockery::mock(Nutgram::class);
        $mockBot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($text) {
                return str_contains($text, 'Внимание: вход с нового IP-адреса')
                    && str_contains($text, '203.0.113.88')
                    && str_contains($text, 'облачного пароля');
            })
            ->andReturn(null);

        $this->app->instance(Nutgram::class, $mockBot);
        $this->app->forgetInstance(SessionSecurityService::class);

        // Attacker or user from new IP 203.0.113.88 tries to access API with token
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.88',
            'HTTP_CF_IPCOUNTRY' => 'NL',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ])->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'ip_verification_required',
            'ip' => '203.0.113.88',
        ]);

        // Verify confirmation key was stored in Redis
        $sessionHash = SessionSecurityService::hashToken($token);
        $pendingJson = Redis::get("security:confirm:{$sessionHash}:203.0.113.88");
        $this->assertNotNull($pendingJson);

        $pendingData = json_decode($pendingJson, true);
        $this->assertNotEmpty($pendingData['code']);
        $this->assertEquals(6, strlen($pendingData['code']));
        $this->assertEquals('203.0.113.88', $pendingData['ip']);
    }

    public function test_confirm_ip_with_valid_key_allows_subsequent_access(): void
    {
        $user = $this->createSecurityUser(444555666);
        $token = Str::random(64);
        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($user, $token, (int) $user->telegram_user_id, 3600, '198.51.100.10', 'Беларусь 🇧🇾 (BY)');

        $securityService = app(SessionSecurityService::class);
        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.88']);
        $verification = $securityService->initiateVerification($user, $token, '203.0.113.88', $request);
        $code = $verification['code'];

        // Confirm IP via API endpoint
        $confirmResponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.88'])
            ->postJson('/api/auth/confirm-ip', [
                'token' => $token,
                'code' => $code,
            ]);

        $confirmResponse->assertStatus(200);
        $confirmResponse->assertJson(['status' => 'ok']);

        // Check that session payload now contains the new IP
        $sessionData = json_decode(Redis::get("session:{$token}"), true);
        $this->assertContains('203.0.113.88', $sessionData['verified_ips']);

        // Now subsequent access from 203.0.113.88 is authorized!
        $meResponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.88'])
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $meResponse->assertStatus(200);
        $meResponse->assertJson(['id' => $user->id]);

        // Audit log was recorded
        $log = ActionLog::where('target_user_id', $user->id)
            ->where('field_name', 'ip_verified')
            ->first();
        $this->assertNotNull($log);
    }

    public function test_invalid_confirmation_key_temporarily_blocks_ip(): void
    {
        $user = $this->createSecurityUser(555666777);
        $token = Str::random(64);
        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($user, $token, (int) $user->telegram_user_id, 3600, '198.51.100.10', 'Беларусь 🇧🇾 (BY)');

        $securityService = app(SessionSecurityService::class);
        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.99']);
        $securityService->initiateVerification($user, $token, '198.51.100.99', $request);

        // Attempt 1 with wrong code
        $resp1 = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->postJson('/api/auth/confirm-ip', [
                'token' => $token,
                'code' => '000000',
            ]);
        $resp1->assertStatus(422);
        $resp1->assertJson(['attempts_left' => 2, 'blocked' => false]);

        // Attempt 2 with wrong code
        $resp2 = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->postJson('/api/auth/confirm-ip', [
                'token' => $token,
                'code' => '111111',
            ]);
        $resp2->assertStatus(422);
        $resp2->assertJson(['attempts_left' => 1, 'blocked' => false]);

        // Attempt 3: threshold reached -> temporary block!
        $resp3 = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->postJson('/api/auth/confirm-ip', [
                'token' => $token,
                'code' => '222222',
            ]);
        $resp3->assertStatus(403);
        $resp3->assertJson(['blocked' => true]);

        // Verify IP is now temporarily blocked in Redis
        $this->assertTrue($securityService->isIpBlocked('198.51.100.99'));

        // Subsequent requests from this IP are rejected with ip_temporarily_blocked
        $blockedResp = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $blockedResp->assertStatus(403);
        $blockedResp->assertJson(['error' => 'ip_temporarily_blocked']);
    }

    public function test_approve_and_block_via_bot_callbacks(): void
    {
        $user = $this->createSecurityUser(666777888);
        $token = Str::random(64);
        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($user, $token, (int) $user->telegram_user_id, 3600, '198.51.100.10', 'Беларусь 🇧🇾 (BY)');

        $securityService = app(SessionSecurityService::class);
        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.77']);
        $verification = $securityService->initiateVerification($user, $token, '203.0.113.77', $request);
        $sessionHash = $verification['session_hash'];
        $code = $verification['code'];

        // Test approveByHash (simulating Telegram bot Approve button)
        $approveResult = $securityService->approveByHash($sessionHash, $code, $user);
        $this->assertTrue($approveResult['success']);
        $this->assertEquals('203.0.113.77', $approveResult['ip']);

        $sessionData = json_decode(Redis::get("session:{$token}"), true);
        $this->assertContains('203.0.113.77', $sessionData['verified_ips']);

        // Now test blockByHash (simulating Telegram bot Block button)
        $blockResult = $securityService->blockByHash($sessionHash, $user);
        $this->assertTrue($blockResult['success']);

        // Token should be revoked from Redis
        $this->assertNull(Redis::get("session:{$token}"));

        // Suspicious IP should be blocked
        $this->assertTrue($securityService->isIpBlocked('203.0.113.77'));
    }

    public function test_user_sessions_endpoint_returns_ip_and_country(): void
    {
        $user = $this->createSecurityUser(777888999);
        $token = Str::random(64);
        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($user, $token, (int) $user->telegram_user_id, 3600, '198.51.100.10', 'Беларусь 🇧🇾 (BY)');

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/sessions');

        $response->assertStatus(200);
        $sessions = $response->json('sessions');
        $this->assertNotEmpty($sessions);
        $this->assertEquals('198.51.100.10', $sessions[0]['ip']);
        $this->assertStringContainsString('Беларусь', $sessions[0]['country']);
    }

    public function test_web_route_with_unverified_ip_renders_verification_view(): void
    {
        $user = $this->createSecurityUser(888999000);
        $token = Str::random(64);
        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($user, $token, (int) $user->telegram_user_id, 3600, '198.51.100.10', 'Беларусь 🇧🇾 (BY)');

        // Access web route with unverified IP and session cookie
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55'])
            ->withSession(['tma_user_id' => $token])
            ->get('/tma/orders');

        $response->assertStatus(403);
        $response->assertViewIs('tma.auth.verify-ip');
        $response->assertSee('203.0.113.55');
    }

    public function test_cloud_password_setup_and_verification_flow(): void
    {
        $user = $this->createSecurityUser(111222999);
        $token = Str::random(64);
        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($user, $token, (int) $user->telegram_user_id, 3600, '198.51.100.10', 'Беларусь 🇧🇾 (BY)');

        // User initially has no cloud password
        $this->assertFalse($user->hasCloudPassword());

        // Setup cloud password via API succeeds
        $setupResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/cloud-password/setup', [
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
                'token' => $token,
            ]);

        $setupResponse->assertStatus(200);
        $setupResponse->assertJson(['status' => 'ok']);

        $user->refresh();
        $this->assertTrue($user->hasCloudPassword());
        $this->assertTrue($user->verifyCloudPassword('secret1234'));
        $this->assertFalse($user->verifyCloudPassword('wrong_password'));

        // Access from new IP 203.0.113.99 triggers verification requirement
        $unverifiedResponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $unverifiedResponse->assertStatus(403);
        $unverifiedResponse->assertJson([
            'error' => 'ip_verification_required',
            'has_cloud_password' => true,
        ]);

        // Verify with correct cloud password
        $verifyResponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/cloud-password/verify', [
                'token' => $token,
                'password' => 'secret1234',
            ]);

        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJson(['status' => 'ok']);

        // Now access from 203.0.113.99 is allowed!
        $allowedResponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $allowedResponse->assertStatus(200);
        $allowedResponse->assertJson(['id' => $user->id]);
    }

    public function test_invalid_cloud_password_attempts_and_rate_limiting(): void
    {
        $user = $this->createSecurityUser(222333444);
        $token = Str::random(64);
        $user->setCloudPassword('correct_pass_123');

        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($user, $token, (int) $user->telegram_user_id, 3600, '198.51.100.10', 'Беларусь 🇧🇾 (BY)');

        $newIp = '203.0.113.123';

        // Attempt 1: wrong password
        $res1 = $this->withServerVariables(['REMOTE_ADDR' => $newIp])
            ->postJson('/api/auth/cloud-password/verify', [
                'token' => $token,
                'password' => 'wrong1',
            ]);
        $res1->assertStatus(422);
        $res1->assertJson(['attempts_left' => 2, 'blocked' => false]);

        // Attempt 2: wrong password
        $res2 = $this->withServerVariables(['REMOTE_ADDR' => $newIp])
            ->postJson('/api/auth/cloud-password/verify', [
                'token' => $token,
                'password' => 'wrong2',
            ]);
        $res2->assertStatus(422);
        $res2->assertJson(['attempts_left' => 1, 'blocked' => false]);

        // Attempt 3: wrong password -> causes temporary block!
        $res3 = $this->withServerVariables(['REMOTE_ADDR' => $newIp])
            ->postJson('/api/auth/cloud-password/verify', [
                'token' => $token,
                'password' => 'wrong3',
            ]);
        $res3->assertStatus(403);
        $res3->assertJson(['blocked' => true]);

        // Check that IP is now blocked
        $securityService = app(SessionSecurityService::class);
        $this->assertTrue($securityService->isIpBlocked($newIp));

        // Even correct password now fails with 403 blocked
        $resBlocked = $this->withServerVariables(['REMOTE_ADDR' => $newIp])
            ->postJson('/api/auth/cloud-password/verify', [
                'token' => $token,
                'password' => 'correct_pass_123',
            ]);
        $resBlocked->assertStatus(403);
    }

    public function test_admin_can_reset_user_cloud_password(): void
    {
        $owner = $this->createSecurityUser(999000111);
        $ownerToken = Str::random(64);
        $sessionService = app(UserSessionService::class);
        $sessionService->registerSession($owner, $ownerToken, (int) $owner->telegram_user_id, 3600, '127.0.0.1');

        $manager = User::create([
            'system_name' => 'Manager User',
            'telegram_user_id' => 888777666,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);
        $manager->setCloudPassword('manager_secret');
        $this->assertTrue($manager->hasCloudPassword());

        // Owner resets manager's cloud password
        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('Authorization', "Bearer {$ownerToken}")
            ->postJson("/api/admin/users/{$manager->id}/reset-cloud-password");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);

        $manager->refresh();
        $this->assertFalse($manager->hasCloudPassword());
    }

    public function test_telegram_alert_callback_data_length_under_64_bytes(): void
    {
        $sessionHash = SessionSecurityService::hashToken(Str::random(64));

        $blockCallback = "sec_blk:{$sessionHash}";

        $this->assertLessThanOrEqual(64, strlen($blockCallback));
        $this->assertEquals(40, strlen($blockCallback));
    }
}
