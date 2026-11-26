<?php

namespace App\Services\Telegram;

use DateTime;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use SergiX44\Nutgram\Hydrator\Hydrator;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Web\WebAppData;
use SergiX44\Nutgram\Telegram\Web\WebAppUser;

class TelegramInitDataValidator
{
    protected Nutgram $bot;

    protected string $botToken;

    protected ?string $lastValidatedInitData = null;

    protected ?WebAppData $lastValidatedData = null;

    public function __construct(?string $token = null, ?Nutgram $bot = null)
    {
        $resolvedToken = $token ?? config('nutgram.token');

        if (! is_string($resolvedToken) || trim($resolvedToken) === '') {
            throw new InvalidArgumentException('TELEGRAM_BOT_TOKEN is not configured');
        }

        $this->botToken = trim($resolvedToken);
        $this->bot = $bot ?? new Nutgram($this->botToken);
    }

    /**
     * Validate Telegram Mini App initData string with strict HMAC-SHA256 signature verification.
     *
     * @throws InvalidArgumentException
     */
    public function validate(string $initData): WebAppData
    {
        $trimmedInitData = trim($initData);
        if ($trimmedInitData === '') {
            throw new InvalidArgumentException('Empty initData');
        }

        if ($this->lastValidatedInitData === $trimmedInitData && $this->lastValidatedData !== null) {
            return $this->lastValidatedData;
        }

        $params = $this->parseInitDataString($trimmedInitData);

        if (! isset($params['hash']) || ! is_string($params['hash']) || strlen($params['hash']) !== 64 || ! ctype_xdigit($params['hash'])) {
            Log::warning('Telegram initData rejected: missing or invalid hash format');
            throw new InvalidArgumentException('Invalid initData');
        }

        $receivedHash = strtolower($params['hash']);

        if (! isset($params['auth_date']) || ! is_numeric($params['auth_date'])) {
            Log::warning('Telegram initData rejected: missing or invalid auth_date');
            throw new InvalidArgumentException('Invalid initData');
        }

        $authDateTimestamp = (int) $params['auth_date'];
        if ($authDateTimestamp <= 0) {
            throw new InvalidArgumentException('Invalid initData');
        }

        $now = time();
        $maxAge = (int) config('services.telegram.init_data_ttl', 3600);
        if ($now - $authDateTimestamp > $maxAge) {
            Log::warning('Telegram initData expired', [
                'auth_date' => date('Y-m-d H:i:s', $authDateTimestamp),
                'now' => date('Y-m-d H:i:s', $now),
                'max_age' => $maxAge,
            ]);

            throw new InvalidArgumentException('Expired initData');
        }

        if ($authDateTimestamp - $now > 120) {
            Log::warning('Telegram initData rejected: timestamp is in the future', [
                'auth_date' => date('Y-m-d H:i:s', $authDateTimestamp),
                'now' => date('Y-m-d H:i:s', $now),
            ]);

            throw new InvalidArgumentException('Invalid initData');
        }

        $checkParams = $params;
        unset($checkParams['hash']);
        ksort($checkParams, SORT_STRING);

        $dataCheckParts = [];
        foreach ($checkParams as $key => $value) {
            $dataCheckParts[] = "{$key}={$value}";
        }
        $dataCheckString = implode("\n", $dataCheckParts);

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($calculatedHash, $receivedHash)) {
            Log::warning('Telegram initData signature verification failed');
            throw new InvalidArgumentException('Invalid initData');
        }

        $hydrated = $this->hydrateWebAppData($params);

        $this->lastValidatedInitData = $trimmedInitData;
        $this->lastValidatedData = $hydrated;

        return $hydrated;
    }

    /**
     * Get validated Telegram user object from initData.
     *
     * @throws InvalidArgumentException
     */
    public function getTelegramUser(string $initData): object
    {
        $data = $this->validate($initData);

        if (! isset($data->user->id)) {
            Log::warning('Telegram initData rejected: missing user.id in payload');
            throw new InvalidArgumentException('User ID not found in initData');
        }

        return $data->user;
    }

    /**
     * Get validated Telegram user ID string from initData.
     *
     * @throws InvalidArgumentException
     */
    public function getTelegramUserId(string $initData): string
    {
        $user = $this->getTelegramUser($initData);

        return (string) $user->id;
    }

    /**
     * Parse query string preserving exact key-value pairs without dropping falsy values.
     *
     * @return array<string, string>
     */
    protected function parseInitDataString(string $queryString): array
    {
        $pairs = explode('&', $queryString);
        $params = [];

        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            $value = isset($parts[1]) ? urldecode($parts[1]) : '';

            $params[$key] = $value;
        }

        return $params;
    }

    /**
     * Hydrate parsed data into a Nutgram WebAppData entity.
     *
     * @param  array<string, mixed>  $params
     */
    protected function hydrateWebAppData(array $params): WebAppData
    {
        try {
            /** @var Hydrator $hydrator */
            $hydrator = $this->bot->getContainer()->get(Hydrator::class);

            /** @var WebAppData $webAppData */
            $webAppData = $hydrator->hydrate($params, WebAppData::class);

            return $webAppData;
        } catch (\Throwable) {
            $webAppData = new WebAppData;
            $webAppData->hash = (string) ($params['hash'] ?? '');
            $webAppData->query_id = isset($params['query_id']) ? (string) $params['query_id'] : null;
            $webAppData->chat_type = isset($params['chat_type']) ? (string) $params['chat_type'] : null;
            $webAppData->chat_instance = isset($params['chat_instance']) ? (string) $params['chat_instance'] : null;
            $webAppData->start_param = isset($params['start_param']) ? (string) $params['start_param'] : null;

            if (isset($params['can_send_after']) && is_numeric($params['can_send_after'])) {
                $webAppData->can_send_after = (int) $params['can_send_after'];
            }

            if (isset($params['auth_date']) && is_numeric($params['auth_date'])) {
                $webAppData->auth_date = (new DateTime)->setTimestamp((int) $params['auth_date']);
            }

            if (isset($params['user'])) {
                $userData = is_string($params['user']) ? json_decode($params['user'], true) : (array) $params['user'];
                if (is_array($userData) && isset($userData['id'])) {
                    $user = new WebAppUser;
                    $user->id = (int) $userData['id'];
                    $user->first_name = (string) ($userData['first_name'] ?? '');
                    $user->last_name = isset($userData['last_name']) ? (string) $userData['last_name'] : null;
                    $user->username = isset($userData['username']) ? (string) $userData['username'] : null;
                    $webAppData->user = $user;
                }
            }

            return $webAppData;
        }
    }
}
