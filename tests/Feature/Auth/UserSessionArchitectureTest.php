<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Telegram\TelegramInitDataValidator;
use App\Services\UserSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class UserSessionArchitectureTest extends TestCase
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

    public function test_tma_auth_reuses_existing_valid_session_token(): void
    {
        $user = User::create([
            'system_name' => 'Manager Jane',
            'telegram_user_id' => 12345678,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $mockValidator = Mockery::mock(TelegramInitDataValidator::class);
        $mockValidator->shouldReceive('getTelegramUserId')
            ->with('valid_init_data')
            ->andReturn('12345678');
        $this->app->instance(TelegramInitDataValidator::class, $mockValidator);

        // 1st login -> creates new session
        $res1 = $this->postJson('/api/auth/tma', [
            'initData' => 'valid_init_data',
        ]);
        $res1->assertOk();
        $token1 = $res1->json('session_token');
        $this->assertNotEmpty($token1);

        // 2nd login passing session_token -> reuses token1
        $res2 = $this->postJson('/api/auth/tma', [
            'initData' => 'valid_init_data',
            'session_token' => $token1,
        ]);
        $res2->assertOk();
        $token2 = $res2->json('session_token');

        $this->assertEquals($token1, $token2);

        // Check that user_sessions set only has 1 token
        $userSessions = Redis::smembers("user_sessions:{$user->id}");
        $this->assertCount(1, $userSessions);
        $this->assertContains($token1, $userSessions);
    }

    public function test_desktop_session_is_not_evicted_by_subsequent_tma_sessions(): void
    {
        Config::set('security.max_tma_sessions', 2);
        Config::set('security.max_desktop_sessions', 3);
        Config::set('security.max_user_sessions', 5);

        $user = User::create([
            'system_name' => 'Owner Alex',
            'telegram_user_id' => 87654321,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        /** @var UserSessionService $sessionService */
        $sessionService = app(UserSessionService::class);

        // 1. Create desktop session
        $desktopToken = 'desktop_token_1234567890_abcdef';
        $sessionService->registerSession(
            $user,
            $desktopToken,
            $user->telegram_user_id,
            3600,
            '192.168.1.50',
            'Беларусь 🇧🇾',
            'desktop'
        );

        $this->assertNotNull(Redis::get("session:{$desktopToken}"));

        // 2. Open TMA 4 times with different tokens (exceeding max_tma_sessions=2)
        $tmaTokens = [];
        for ($i = 1; $i <= 4; $i++) {
            $tToken = "tma_token_test_{$i}_" . bin2hex(random_bytes(16));
            $tmaTokens[] = $tToken;
            $sessionService->registerSession(
                $user,
                $tToken,
                $user->telegram_user_id,
                3600,
                '192.168.1.10',
                'Беларусь 🇧🇾',
                'tma'
            );
        }

        // 3. Desktop session MUST still exist!
        $this->assertNotNull(Redis::get("session:{$desktopToken}"), 'Desktop session should not be evicted by TMA sessions');

        // 4. Check user_sessions set: desktop token + last 2 TMA tokens (total 3)
        $activeSessions = Redis::smembers("user_sessions:{$user->id}");
        $this->assertContains($desktopToken, $activeSessions);
        $this->assertContains($tmaTokens[3], $activeSessions);
        $this->assertContains($tmaTokens[2], $activeSessions);
        $this->assertNotContains($tmaTokens[0], $activeSessions, 'Oldest TMA session should be pruned');
        $this->assertNotContains($tmaTokens[1], $activeSessions, 'Second oldest TMA session should be pruned');
    }

    public function test_desktop_sessions_prune_only_oldest_desktop_sessions(): void
    {
        Config::set('security.max_desktop_sessions', 2);
        Config::set('security.max_tma_sessions', 2);

        $user = User::create([
            'system_name' => 'Courier Max',
            'telegram_user_id' => 55566677,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        /** @var UserSessionService $sessionService */
        $sessionService = app(UserSessionService::class);

        // TMA session
        $tmaToken = 'tma_token_stable_123';
        $sessionService->registerSession($user, $tmaToken, $user->telegram_user_id, 3600, '127.0.0.1', 'Local', 'tma');

        // 3 desktop sessions (exceeding max_desktop_sessions=2)
        $d1 = 'desktop_1_' . bin2hex(random_bytes(16));
        $sessionService->registerSession($user, $d1, $user->telegram_user_id, 3600, '127.0.0.1', 'Local', 'desktop');

        $d2 = 'desktop_2_' . bin2hex(random_bytes(16));
        $sessionService->registerSession($user, $d2, $user->telegram_user_id, 3600, '127.0.0.1', 'Local', 'desktop');

        $d3 = 'desktop_3_' . bin2hex(random_bytes(16));
        $sessionService->registerSession($user, $d3, $user->telegram_user_id, 3600, '127.0.0.1', 'Local', 'desktop');

        $active = Redis::smembers("user_sessions:{$user->id}");

        // TMA session is untouched
        $this->assertContains($tmaToken, $active);
        // Oldest desktop session $d1 is pruned
        $this->assertNotContains($d1, $active);
        // Newer desktop sessions $d2 and $d3 are active
        $this->assertContains($d2, $active);
        $this->assertContains($d3, $active);
    }

    public function test_logging_out_from_desktop_does_not_revoke_tma_session(): void
    {
        $user = User::create([
            'system_name' => 'Manager Leo',
            'telegram_user_id' => 99988877,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        /** @var UserSessionService $sessionService */
        $sessionService = app(UserSessionService::class);

        // TMA session
        $tmaToken = 'tma_active_token_' . bin2hex(random_bytes(16));
        $sessionService->registerSession($user, $tmaToken, $user->telegram_user_id, 3600, '127.0.0.1', 'Local', 'tma');

        // Desktop session
        $desktopToken = 'desktop_active_token_' . bin2hex(random_bytes(16));
        $sessionService->registerSession($user, $desktopToken, $user->telegram_user_id, 3600, '192.168.1.5', 'Local', 'desktop');

        // User logs out on Desktop
        $response = $this->withHeader('Authorization', "Bearer {$desktopToken}")
            ->postJson('/api/auth/logout');

        $response->assertOk();

        // Desktop session is revoked
        $this->assertNull(Redis::get("session:{$desktopToken}"));
        $this->assertNull(Redis::get("session_data:{$desktopToken}"));

        // TMA session in Redis MUST remain active!
        $this->assertNotNull(Redis::get("session:{$tmaToken}"), 'TMA session should remain valid after desktop logout');
        $this->assertNotNull(Redis::get("session_data:{$tmaToken}"));
        $this->assertNotNull(Redis::get("session:{$user->telegram_user_id}"));

        $remainingSessions = Redis::smembers("user_sessions:{$user->id}");
        $this->assertContains($tmaToken, $remainingSessions);
        $this->assertNotContains($desktopToken, $remainingSessions);
    }

    public function test_desktop_token_cannot_be_reused_as_tma_session(): void
    {
        $user = User::create([
            'system_name' => 'Owner Maria',
            'telegram_user_id' => 44433322,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        /** @var UserSessionService $sessionService */
        $sessionService = app(UserSessionService::class);

        $desktopToken = 'desktop_special_' . bin2hex(random_bytes(16));
        $sessionService->registerSession($user, $desktopToken, $user->telegram_user_id, 3600, '192.168.1.5', 'Local', 'desktop');

        $mockValidator = Mockery::mock(TelegramInitDataValidator::class);
        $mockValidator->shouldReceive('getTelegramUserId')
            ->with('valid_init_data')
            ->andReturn('44433322');
        $this->app->instance(TelegramInitDataValidator::class, $mockValidator);

        // Try to pass desktopToken to TMA auth
        $res = $this->postJson('/api/auth/tma', [
            'initData' => 'valid_init_data',
            'session_token' => $desktopToken,
        ]);

        $res->assertOk();
        $returnedToken = $res->json('session_token');

        // It should NOT reuse desktopToken, but generate a new TMA token!
        $this->assertNotEquals($desktopToken, $returnedToken);

        // Desktop token still exists with device_type desktop
        $desktopData = json_decode(Redis::get("session:{$desktopToken}"), true);
        $this->assertEquals('desktop', $desktopData['device_type']);

        // Returned token is TMA
        $tmaData = json_decode(Redis::get("session:{$returnedToken}"), true);
        $this->assertEquals('tma', $tmaData['device_type']);
    }
}
