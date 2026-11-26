<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\IpUtils;

class GeoIpService
{
    public const CACHE_TTL = 604800; // 7 days in seconds

    public const NEGATIVE_CACHE_TTL = 3600; // 1 hour for failed lookups

    /**
     * Common ISO alpha-2 country codes mapped to Russian names with flags.
     *
     * @var array<string, string>
     */
    protected static array $countryNames = [
        'RU' => 'Россия 🇷🇺 (RU)',
        'BY' => 'Беларусь 🇧🇾 (BY)',
        'KZ' => 'Казахстан 🇰🇿 (KZ)',
        'UA' => 'Украина 🇺🇦 (UA)',
        'UZ' => 'Узбекистан 🇺🇿 (UZ)',
        'GE' => 'Грузия 🇬🇪 (GE)',
        'AM' => 'Армения 🇦🇲 (AM)',
        'AZ' => 'Азербайджан 🇦🇿 (AZ)',
        'KG' => 'Кыргызстан 🇰🇬 (KG)',
        'MD' => 'Молдова 🇲🇩 (MD)',
        'TJ' => 'Таджикистан 🇹🇯 (TJ)',
        'TM' => 'Туркменистан 🇹🇲 (TM)',
        'PL' => 'Польша 🇵🇱 (PL)',
        'DE' => 'Германия 🇩🇪 (DE)',
        'US' => 'США 🇺🇸 (US)',
        'TR' => 'Турция 🇹🇷 (TR)',
        'NL' => 'Нидерланды 🇳🇱 (NL)',
        'GB' => 'Великобритания 🇬🇧 (GB)',
        'FR' => 'Франция 🇫🇷 (FR)',
        'IT' => 'Италия 🇮🇹 (IT)',
        'ES' => 'Испания 🇪🇸 (ES)',
        'CN' => 'Китай 🇨🇳 (CN)',
        'LT' => 'Литва 🇱🇹 (LT)',
        'LV' => 'Латвия 🇱🇻 (LV)',
        'EE' => 'Эстония 🇪🇪 (EE)',
        'FI' => 'Финляндия 🇫🇮 (FI)',
        'SE' => 'Швеция 🇸🇪 (SE)',
        'NO' => 'Норвегия 🇳🇴 (NO)',
        'AE' => 'ОАЭ 🇦🇪 (AE)',
        'IL' => 'Израиль 🇮🇱 (IL)',
        'CY' => 'Кипр 🇨🇾 (CY)',
        'RS' => 'Сербия 🇷🇸 (RS)',
        'ME' => 'Черногория 🇲🇪 (ME)',
        'TH' => 'Таиланд 🇹🇭 (TH)',
        'VN' => 'Вьетнам 🇻🇳 (VN)',
        'ID' => 'Индонезия 🇮🇩 (ID)',
        'BR' => 'Бразилия 🇧🇷 (BR)',
        'CA' => 'Канада 🇨🇦 (CA)',
        'CZ' => 'Чехия 🇨🇿 (CZ)',
        'AT' => 'Австрия 🇦🇹 (AT)',
        'CH' => 'Швейцария 🇨🇭 (CH)',
    ];

    /**
     * Resolve the most accurate client IP address from request headers.
     */
    public function resolveClientIp(?Request $request = null): string
    {
        $request = $request ?? request();

        if (! $request) {
            return '127.0.0.1';
        }

        if ($this->isRequestFromTrustedProxy($request)) {
            $cfIp = $request->header('CF-Connecting-IP');
            if (is_string($cfIp) && filter_var($cfIp, FILTER_VALIDATE_IP)) {
                return $cfIp;
            }

            $xff = $request->header('X-Forwarded-For');
            if (is_string($xff)) {
                $firstXff = trim(explode(',', $xff)[0]);
                if (filter_var($firstXff, FILTER_VALIDATE_IP)) {
                    return $firstXff;
                }
            }
        }

        $ip = $request->ip();

        return ($ip && filter_var($ip, FILTER_VALIDATE_IP)) ? $ip : '127.0.0.1';
    }

    /**
     * Resolve country information for an IP address.
     *
     * @return array{code: ?string, name: string}
     */
    public function resolveCountry(string $ip, ?Request $request = null): array
    {
        $request = $request ?? request();

        if ($request && ($this->isRequestFromTrustedProxy($request) || app()->runningUnitTests())) {
            $cfCountry = $request->header('CF-IPCountry');
            if (is_string($cfCountry) && strlen(trim($cfCountry)) === 2 && strtoupper(trim($cfCountry)) !== 'XX') {
                $code = strtoupper(trim($cfCountry));

                return [
                    'code' => $code,
                    'name' => $this->formatCountryName($code),
                ];
            }

            $xCountry = $request->header('X-Country-Code');
            if (is_string($xCountry) && strlen(trim($xCountry)) === 2) {
                $code = strtoupper(trim($xCountry));

                return [
                    'code' => $code,
                    'name' => $this->formatCountryName($code),
                ];
            }
        }

        if ($this->isPrivateOrReservedIp($ip)) {
            return [
                'code' => 'LOCAL',
                'name' => 'Локальная сеть 🏠',
            ];
        }

        $cacheKey = "geoip:ip:{$ip}";
        try {
            $cached = Redis::get($cacheKey);
            if ($cached) {
                $decoded = json_decode((string) $cached, true);
                if (is_array($decoded) && isset($decoded['name'])) {
                    return $decoded;
                }
            }
        } catch (\Throwable) {
        }

        $result = $this->lookupExternalGeoIp($ip);
        $ttl = ($result['code'] !== null) ? self::CACHE_TTL : self::NEGATIVE_CACHE_TTL;

        try {
            Redis::setex($cacheKey, $ttl, (string) json_encode($result));
        } catch (\Throwable) {
        }

        return $result;
    }

    /**
     * Helper to get formatted country name directly.
     */
    public function resolveCountryName(string $ip, ?Request $request = null): string
    {
        return $this->resolveCountry($ip, $request)['name'];
    }

    /**
     * Map country code to friendly name with flag emoji.
     */
    public function formatCountryName(?string $code): string
    {
        if (! $code) {
            return 'Не определена 🌐';
        }

        $upper = strtoupper(trim($code));

        if (isset(self::$countryNames[$upper])) {
            return self::$countryNames[$upper];
        }

        return "{$upper} 🌐";
    }

    /**
     * Check if IP is private, reserved or loopback.
     */
    public function isPrivateOrReservedIp(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
            return true;
        }

        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Query external free GeoIP API with short timeout and fallback.
     *
     * @return array{code: ?string, name: string}
     */
    protected function lookupExternalGeoIp(string $ip): array
    {
        if (app()->runningUnitTests()) {
            return [
                'code' => null,
                'name' => 'Не определена 🌐',
            ];
        }

        try {
            $response = Http::timeout(1.5)
                ->acceptJson()
                ->get("https://ipwho.is/{$ip}");

            if ($response->successful()) {
                $data = $response->json();
                if (($data['success'] ?? false) === true && ! empty($data['country_code'])) {
                    $code = strtoupper((string) $data['country_code']);

                    return [
                        'code' => $code,
                        'name' => $this->formatCountryName($code),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::debug("GeoIP lookup failed for IP {$ip}: ".$e->getMessage());
        }

        return [
            'code' => null,
            'name' => 'Не определена 🌐',
        ];
    }

    protected function isRequestFromTrustedProxy(Request $request): bool
    {
        if ($request->isFromTrustedProxy()) {
            return true;
        }

        $remoteAddr = (string) $request->server->get('REMOTE_ADDR', '');
        if ($remoteAddr === '' || ! filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return false;
        }

        $trustedProxies = (array) config('security.trusted_proxies', [
            '127.0.0.1',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);

        return $trustedProxies !== [] && IpUtils::checkIp($remoteAddr, $trustedProxies);
    }
}
