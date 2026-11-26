<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Telegram\TelegramInitDataValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class TmaAuthTest extends TestCase
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

    public function test_auth_fails_when_init_data_invalid(): void
    {
        $mockValidator = Mockery::mock(TelegramInitDataValidator::class);
        $mockValidator->shouldReceive('getTelegramUserId')
            ->with('invalid_init_data')
            ->andThrow(new \InvalidArgumentException('Invalid initData'));

        $this->app->instance(TelegramInitDataValidator::class, $mockValidator);

        $response = $this->postJson('/api/auth/tma', [
            'initData' => 'invalid_init_data',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Invalid initData']);
    }

    public function test_auth_fails_when_user_does_not_exist_or_inactive(): void
    {
        $mockValidator = Mockery::mock(TelegramInitDataValidator::class);
        $mockValidator->shouldReceive('getTelegramUserId')
            ->with('valid_unknown_user_init_data')
            ->andReturn('99999999');

        $this->app->instance(TelegramInitDataValidator::class, $mockValidator);

        $response = $this->postJson('/api/auth/tma', [
            'initData' => 'valid_unknown_user_init_data',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'User not allowed']);
    }

    public function test_auth_success_creates_session_in_redis(): void
    {
        $user = User::create([
            'system_name' => 'Admin User',
            'telegram_user_id' => 777777,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $mockValidator = Mockery::mock(TelegramInitDataValidator::class);
        $mockValidator->shouldReceive('getTelegramUserId')
            ->with('valid_init_data')
            ->andReturn('777777');

        $this->app->instance(TelegramInitDataValidator::class, $mockValidator);

        $response = $this->postJson('/api/auth/tma', [
            'initData' => 'valid_init_data',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $user->id,
            'role' => 'owner',
            'system_name' => 'Admin User',
            'telegram_user_id' => '777777',
        ]);

        $token = $response->json('session_token');
        $this->assertNotEmpty($token);

        $sessionData = Redis::get("session:{$token}");
        $this->assertNotNull($sessionData);
        $decoded = json_decode($sessionData, true);
        $this->assertEquals($user->id, $decoded['user_id']);
    }

    public function test_logout_removes_session_from_redis(): void
    {
        $user = User::create([
            'system_name' => 'Courier Sam',
            'telegram_user_id' => 888888,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $token = 'test_session_token_123456';
        Redis::setex("session:{$token}", 3600, json_encode([
            'user_id' => $user->id,
            'telegram_user_id' => $user->telegram_user_id,
            'role' => $user->role->value,
        ]));
        Redis::setex("session:{$user->telegram_user_id}", 3600, json_encode(['token' => $token]));

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
        $this->assertNull(Redis::get("session:{$token}"));
        $this->assertNull(Redis::get("session:{$user->telegram_user_id}"));
    }

    public function test_sessions_list_masks_raw_tokens_and_allows_revocation_by_hash(): void
    {
        $user = User::create([
            'system_name' => 'Owner Mark',
            'telegram_user_id' => 999111,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $currentToken = 'raw_bearer_token_current_1234567890';
        $otherToken = 'raw_bearer_token_other_9876543210';

        $sessionPayloadCurrent = json_encode([
            'user_id' => $user->id,
            'telegram_user_id' => $user->telegram_user_id,
            'role' => $user->role->value,
            'token' => $currentToken,
            'created_at' => now()->timestamp,
        ]);
        $sessionPayloadOther = json_encode([
            'user_id' => $user->id,
            'telegram_user_id' => $user->telegram_user_id,
            'role' => $user->role->value,
            'token' => $otherToken,
            'created_at' => now()->timestamp,
        ]);

        Redis::setex("session:{$currentToken}", 3600, $sessionPayloadCurrent);
        Redis::setex("session_data:{$currentToken}", 3600, $sessionPayloadCurrent);
        Redis::setex("session:{$otherToken}", 3600, $sessionPayloadOther);
        Redis::setex("session_data:{$otherToken}", 3600, $sessionPayloadOther);
        Redis::sadd("user_sessions:{$user->id}", $currentToken);
        Redis::sadd("user_sessions:{$user->id}", $otherToken);

        $res = $this->withHeader('Authorization', "Bearer {$currentToken}")
            ->getJson('/api/auth/sessions');

        $res->assertOk();
        $sessions = $res->json('sessions');
        $this->assertCount(2, $sessions);

        foreach ($sessions as $s) {
            $this->assertNotEquals($currentToken, $s['token']);
            $this->assertNotEquals($otherToken, $s['token']);
        }

        $otherSession = collect($sessions)->firstWhere('is_current', false);
        $this->assertNotNull($otherSession);
        $otherHash = $otherSession['token'];

        $currentSession = collect($sessions)->firstWhere('is_current', true);
        $revokeCurrentRes = $this->withHeader('Authorization', "Bearer {$currentToken}")
            ->deleteJson('/api/auth/sessions', ['token' => $currentSession['token']]);
        $revokeCurrentRes->assertStatus(422);

        $revokeRes = $this->withHeader('Authorization', "Bearer {$currentToken}")
            ->deleteJson('/api/auth/sessions', ['token' => $otherHash]);
        $revokeRes->assertOk();

        $this->assertNull(Redis::get("session:{$otherToken}"));
        $this->assertNotNull(Redis::get("session:{$currentToken}"));
    }

    public function test_real_hmac_valid_init_data_authenticates(): void
    {
        $botToken = '987654:TEST_BOT_TOKEN_ABCXYZ123456';
        Config::set('nutgram.token', $botToken);

        $user = User::create([
            'system_name' => 'Real Auth User',
            'telegram_user_id' => 333444555,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $signedInitData = $this->createSignedInitData([
            'auth_date' => time(),
            'query_id' => 'AAHdF60gAAAAAN0XrSB21844',
            'user' => json_encode([
                'id' => 333444555,
                'first_name' => 'RealUser',
                'username' => 'real_user',
            ]),
        ], $botToken);

        $response = $this->postJson('/api/auth/tma', [
            'initData' => $signedInitData,
        ]);

        $response->assertOk();
        $response->assertJson([
            'id' => $user->id,
            'role' => 'manager',
            'telegram_user_id' => '333444555',
        ]);
        $this->assertNotEmpty($response->json('session_token'));
    }

    public function test_real_hmac_tampered_payload_rejected_with_403(): void
    {
        $botToken = '987654:TEST_BOT_TOKEN_ABCXYZ123456';
        Config::set('nutgram.token', $botToken);

        User::create([
            'system_name' => 'Target User',
            'telegram_user_id' => 333444555,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $signedInitData = $this->createSignedInitData([
            'auth_date' => time(),
            'query_id' => 'AAHdF60gAAAAAN0XrSB21844',
            'user' => json_encode(['id' => 111, 'first_name' => 'Hacker']),
        ], $botToken);

        // Tamper with user ID in URL query without re-signing
        $tampered = str_replace('111', '333444555', $signedInitData);

        $response = $this->postJson('/api/auth/tma', [
            'initData' => $tampered,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Invalid initData']);
    }

    public function test_real_hmac_expired_init_data_rejected_with_403(): void
    {
        $botToken = '987654:TEST_BOT_TOKEN_ABCXYZ123456';
        Config::set('nutgram.token', $botToken);

        User::create([
            'system_name' => 'Target User',
            'telegram_user_id' => 333444555,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $expiredInitData = $this->createSignedInitData([
            'auth_date' => time() - (config('services.telegram.init_data_ttl', 86400) + 3600),
            'query_id' => 'AAHdF60gAAAAAN0XrSB21844',
            'user' => json_encode(['id' => 333444555, 'first_name' => 'User']),
        ], $botToken);

        $response = $this->postJson('/api/auth/tma', [
            'initData' => $expiredInitData,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Invalid initData']);
    }

    protected function createSignedInitData(array $params, string $botToken): string
    {
        ksort($params, SORT_STRING);
        $dataCheckParts = [];
        foreach ($params as $k => $v) {
            $dataCheckParts[] = "{$k}={$v}";
        }
        $dataCheckString = implode("\n", $dataCheckParts);

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);
        $params['hash'] = $hash;

        $queryParts = [];
        foreach ($params as $k => $v) {
            $queryParts[] = urlencode((string) $k).'='.urlencode((string) $v);
        }

        return implode('&', $queryParts);
    }
}
