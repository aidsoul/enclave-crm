<?php

namespace Tests\Unit\Services\Telegram;

use App\Services\Telegram\TelegramInitDataValidator;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

class TelegramInitDataValidatorTest extends TestCase
{
    protected string $botToken = '123456789:ABCdefGHIjklMNOpqrsTUVwxyz_1234567';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('nutgram.token', $this->botToken);
        Config::set('services.telegram.init_data_ttl', 3600);
    }

    /**
     * Helper to generate a valid Telegram Mini App initData string signed with HMAC-SHA256.
     *
     * @param  array<string, mixed>  $params
     */
    protected function generateInitData(array $params, ?string $token = null): string
    {
        $signingToken = $token ?? $this->botToken;

        ksort($params, SORT_STRING);
        $dataCheckParts = [];
        foreach ($params as $k => $v) {
            $dataCheckParts[] = "{$k}={$v}";
        }
        $dataCheckString = implode("\n", $dataCheckParts);

        $secretKey = hash_hmac('sha256', $signingToken, 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $params['hash'] = $hash;

        $queryParts = [];
        foreach ($params as $k => $v) {
            $queryParts[] = urlencode((string) $k).'='.urlencode((string) $v);
        }

        return implode('&', $queryParts);
    }

    public function test_valid_init_data_passes_verification(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $userJson = json_encode([
            'id' => 987654321,
            'first_name' => 'Александр',
            'username' => 'alex_courier',
        ]);

        $initData = $this->generateInitData([
            'auth_date' => time(),
            'query_id' => 'AAHdF60gAAAAAN0XrSB21844',
            'user' => $userJson,
        ]);

        $webAppData = $validator->validate($initData);

        $this->assertEquals('AAHdF60gAAAAAN0XrSB21844', $webAppData->query_id);
        $this->assertEquals(987654321, $webAppData->user->id);
        $this->assertEquals('alex_courier', $webAppData->user->username);
        $this->assertEquals('987654321', $validator->getTelegramUserId($initData));

        $user = $validator->getTelegramUser($initData);
        $this->assertEquals(987654321, $user->id);
    }

    public function test_init_data_with_falsy_parameters_is_preserved_and_verified(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $userJson = json_encode([
            'id' => 555666777,
            'first_name' => 'Тест',
        ]);

        $initData = $this->generateInitData([
            'auth_date' => time(),
            'can_send_after' => '0',
            'query_id' => 'AAHdF60gAAAAAN0XrSB21844',
            'user' => $userJson,
        ]);

        $webAppData = $validator->validate($initData);
        $this->assertEquals(555666777, $webAppData->user->id);
    }

    public function test_tampered_payload_fails_verification(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $initData = $this->generateInitData([
            'auth_date' => time(),
            'query_id' => 'AAHdF60gAAAAAN0XrSB21844',
            'user' => json_encode(['id' => 111, 'username' => 'legit_user']),
        ]);

        // Tamper with user parameter without updating hash
        $tampered = str_replace('legit_user', 'attacker', $initData);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid initData');

        $validator->validate($tampered);
    }

    public function test_tampered_hash_fails_verification(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $initData = $this->generateInitData([
            'auth_date' => time(),
            'query_id' => 'AAHdF60gAAAAAN0XrSB21844',
            'user' => json_encode(['id' => 111]),
        ]);

        // Replace valid hash with an invalid 64-char hex hash
        $tampered = preg_replace('/hash=[a-f0-9]{64}/', 'hash='.str_repeat('a', 64), $initData);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid initData');

        $validator->validate($tampered);
    }

    public function test_data_signed_with_different_token_fails_verification(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $initData = $this->generateInitData([
            'auth_date' => time(),
            'query_id' => 'AAHdF60gAAAAAN0XrSB21844',
            'user' => json_encode(['id' => 111]),
        ], 'different_bot_token_999999');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid initData');

        $validator->validate($initData);
    }

    public function test_missing_hash_fails_verification(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $initData = 'auth_date='.time().'&user='.urlencode(json_encode(['id' => 123]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid initData');

        $validator->validate($initData);
    }

    public function test_malformed_hash_fails_verification(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $initData = 'auth_date='.time().'&hash=not_a_valid_hex_hash';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid initData');

        $validator->validate($initData);
    }

    public function test_missing_auth_date_fails_verification(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $initData = 'hash='.str_repeat('b', 64).'&user='.urlencode(json_encode(['id' => 123]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid initData');

        $validator->validate($initData);
    }

    public function test_expired_init_data_fails_verification(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $expiredTime = time() - 3601; // Older than 1 hour TTL
        $initData = $this->generateInitData([
            'auth_date' => $expiredTime,
            'user' => json_encode(['id' => 123]),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expired initData');

        $validator->validate($initData);
    }

    public function test_future_auth_date_fails_verification(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $futureTime = time() + 300; // 5 minutes in future (exceeds 120s tolerance)
        $initData = $this->generateInitData([
            'auth_date' => $futureTime,
            'user' => json_encode(['id' => 123]),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid initData');

        $validator->validate($initData);
    }

    public function test_missing_user_id_in_payload_fails_on_get_user(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $initData = $this->generateInitData([
            'auth_date' => time(),
            'query_id' => 'AAHdF60gAAAAAN0XrSB21844',
            'user' => json_encode(['first_name' => 'NoIdUser']),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID not found in initData');

        $validator->getTelegramUser($initData);
    }

    public function test_unconfigured_bot_token_throws_exception(): void
    {
        Config::set('nutgram.token', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TELEGRAM_BOT_TOKEN is not configured');

        new TelegramInitDataValidator(null);
    }

    public function test_consecutive_calls_with_identical_init_data_use_cached_result(): void
    {
        $validator = new TelegramInitDataValidator($this->botToken);

        $initData = $this->generateInitData([
            'auth_date' => time(),
            'user' => json_encode(['id' => 444555, 'first_name' => 'CacheTest']),
        ]);

        $res1 = $validator->validate($initData);
        $res2 = $validator->validate($initData);

        $this->assertSame($res1, $res2);
    }
}
