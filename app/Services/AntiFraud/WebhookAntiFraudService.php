<?php

namespace App\Services\AntiFraud;

use App\Dto\AntiFraud\AntiFraudResult;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebhookAntiFraudService
{
    protected const DISPOSABLE_DOMAINS = [
        'mailinator.com' => true,
        '10minutemail.com' => true,
        'tempmail.com' => true,
        'trashmail.com' => true,
        'guerrillamail.com' => true,
        'sharklasers.com' => true,
        'yopmail.com' => true,
        'dispostable.com' => true,
        'getairmail.com' => true,
        'dropmail.me' => true,
        'temp-mail.org' => true,
        'fakeinbox.com' => true,
        'nada.ltd' => true,
        'crazymailing.com' => true,
        'mohmal.com' => true,
        'burnermail.io' => true,
    ];

    protected const HONEYPOT_FIELDS = [
        '_honey',
        'website',
        'hp_comment',
        'address_line2',
        'fax',
        'company_fax',
    ];

    protected const BAD_BOT_PATTERNS = [
        'curl/',
        'python-requests',
        'wget/',
        'postmanruntime',
        'scrapy',
        'aiohttp',
        'httpclient',
        'masscan',
        'nikto',
        'sqlmap',
        'havij',
        'acunetix',
        'morfeus',
        'zgrab',
        'censys',
    ];

    public function __construct(
        protected FingerprintResolver $fingerprintResolver
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function evaluate(Site $site, Request $request, array $data): AntiFraudResult
    {
        $resolved = $this->fingerprintResolver->resolve($request, $data);
        $fingerprint = $resolved['fingerprint'];
        $clientIp = $resolved['clientIp'];
        $physicalIp = $resolved['physicalIp'];
        $userAgent = $resolved['userAgent'];
        $acceptLanguage = $resolved['acceptLanguage'];

        $score = 0;
        $flags = [];
        $reasons = [];

        // 1. Honeypot form field check
        if ($this->isHoneypotTriggered($request, $data)) {
            $score += 100;
            $flags[] = 'honeypot_trap';
            $reasons[] = 'Honeypot form field filled';
        }

        // 2. Bad automated user agent check
        if ($this->isBadUserAgent($userAgent)) {
            $score += 80;
            $flags[] = 'bad_user_agent';
            $reasons[] = 'Automated bot user-agent detected';
        }

        // 3. Disposable throwaway email check
        $email = $data['client_email'] ?? ($data['email'] ?? null);
        if ($email && $this->isDisposableEmail($email)) {
            $score += 80;
            $flags[] = 'disposable_email';
            $reasons[] = 'Disposable email address detected';
        }

        // 4. Suspicious dummy phone check
        $phone = $data['client_phone'] ?? ($data['phone'] ?? null);
        if ($phone && $this->isSuspiciousPhone($phone)) {
            $score += 80;
            $flags[] = 'suspicious_phone';
            $reasons[] = 'Suspicious or dummy phone number pattern';
        }

        // 5. Rapid phone rotation check (SMS bombing / phone spraying attack)
        if ($phone && $this->isRapidPhoneRotation($site->id, $fingerprint, $phone)) {
            $score += 85;
            $flags[] = 'rapid_phone_rotation';
            $reasons[] = 'Multiple different phone numbers submitted from single device';
        }

        // 6. Velocity burst check
        if ($this->isVelocityBurst($site->id, $fingerprint)) {
            $score += 60;
            $flags[] = 'velocity_burst';
            $reasons[] = 'Submission frequency exceeds burst threshold';
        }

        // 7. Spam text / phishing URL heuristics
        $name = $data['client_name'] ?? ($data['name'] ?? null);
        $message = $data['message'] ?? ($data['comment'] ?? ($data['client_comment'] ?? null));
        if ($this->containsSpamText($name, $message)) {
            $score += 80;
            $flags[] = 'spam_text';
            $reasons[] = 'Spam URLs or HTML markup detected in message or name';
        }

        return new AntiFraudResult(
            score: min($score, 100),
            flags: $flags,
            reasons: $reasons,
            fingerprint: $fingerprint,
            clientIp: $clientIp,
            physicalIp: $physicalIp,
            userAgent: $userAgent,
            acceptLanguage: $acceptLanguage
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function isHoneypotTriggered(Request $request, array $data): bool
    {
        foreach (self::HONEYPOT_FIELDS as $field) {
            $val = $data[$field] ?? $request->input($field);
            if (! empty($val)) {
                return true;
            }
        }

        return false;
    }

    protected function isBadUserAgent(string $userAgent): bool
    {
        if ($userAgent === '' || $userAgent === 'unknown') {
            return false;
        }

        if (str_starts_with($userAgent, 'Symfony')) {
            return false;
        }

        $lowerUa = strtolower($userAgent);
        foreach (self::BAD_BOT_PATTERNS as $bot) {
            if (str_contains($lowerUa, $bot)) {
                return true;
            }
        }

        return false;
    }

    protected function isDisposableEmail(string $email): bool
    {
        $parts = explode('@', trim($email));
        if (count($parts) !== 2) {
            return false;
        }

        $domain = strtolower($parts[1]);

        return isset(self::DISPOSABLE_DOMAINS[$domain]);
    }

    protected function isSuspiciousPhone(string $phone): bool
    {
        $digits = preg_replace('/\D/', '', $phone);
        $len = strlen($digits);

        if ($len < 7 || $len > 15) {
            return true;
        }

        if (preg_match('/^(\d)\1+$/', $digits) || preg_match('/(\d)\1{5,}$/', $digits)) {
            return true;
        }

        if (in_array($digits, ['12345678', '123456789', '0123456789', '987654321', '9876543210'], true)) {
            return true;
        }

        return false;
    }

    protected function isRapidPhoneRotation(int $siteId, string $fingerprint, string $phone): bool
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (! $digits) {
            return false;
        }

        $cacheKey = "af:phones:{$siteId}:{$fingerprint}";
        $lockKey = "af:lock:phones:{$siteId}:{$fingerprint}";

        return (bool) Cache::lock($lockKey, 3)->get(function () use ($cacheKey, $digits) {
            /** @var array<string, int> $phones */
            $phones = Cache::get($cacheKey, []);
            $phones[$digits] = time();

            $cutoff = time() - 600;
            foreach ($phones as $k => $ts) {
                if ($ts < $cutoff) {
                    unset($phones[$k]);
                }
            }

            if (count($phones) > 10) {
                $phones = array_slice($phones, -10, null, true);
            }

            Cache::put($cacheKey, $phones, 600);

            return count($phones) >= 3;
        });
    }

    protected function isVelocityBurst(int $siteId, string $fingerprint): bool
    {
        $cacheKey = "af:vel:{$siteId}:{$fingerprint}";
        $added = Cache::add($cacheKey, 1, 300);
        if (! $added) {
            $count = Cache::increment($cacheKey);

            return $count > 5;
        }

        return false;
    }

    protected function containsSpamText(?string $name, ?string $message): bool
    {
        if ($name && preg_match('/(https?:\/\/|www\.|\.ru\/|\.com\/|<a |\[url=)/i', $name)) {
            return true;
        }

        if ($message) {
            if (preg_match('/(\[url=|href=|<a )/i', $message)) {
                return true;
            }
            if (substr_count(mb_strtolower($message), 'http') > 2) {
                return true;
            }
        }

        return false;
    }
}
