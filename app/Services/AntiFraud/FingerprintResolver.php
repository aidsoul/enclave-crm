<?php

namespace App\Services\AntiFraud;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FingerprintResolver
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{fingerprint: string, clientIp: string, physicalIp: string, userAgent: string, acceptLanguage: string}
     */
    public function resolve(Request $request, array $data = []): array
    {
        $physicalIp = $request->ip() ?: '127.0.0.1';
        $clientIp = null;

        $candidateIp = $data['client_ip'] ?? $request->input('client_ip');
        if (is_string($candidateIp) && filter_var($candidateIp, FILTER_VALIDATE_IP)) {
            $clientIp = $candidateIp;
        }

        if (! $clientIp && $request->isFromTrustedProxy()) {
            $cfIp = $request->header('CF-Connecting-IP');
            if (is_string($cfIp) && filter_var($cfIp, FILTER_VALIDATE_IP)) {
                $clientIp = $cfIp;
            }
        }

        if (! $clientIp && $request->isFromTrustedProxy()) {
            $xff = $request->header('X-Forwarded-For');
            if (is_string($xff)) {
                $firstXff = trim(explode(',', $xff)[0]);
                if (filter_var($firstXff, FILTER_VALIDATE_IP)) {
                    $clientIp = $firstXff;
                }
            }
        }

        if (! $clientIp) {
            $clientIp = $physicalIp;
        }

        $userAgent = (string) ($data['user_agent'] ?? $request->input('user_agent') ?? $request->userAgent() ?? 'unknown');
        $userAgent = trim($userAgent);

        $acceptLanguage = (string) ($data['accept_language'] ?? $request->input('accept_language') ?? $request->header('Accept-Language') ?? '');
        $acceptLanguage = trim($acceptLanguage);

        $explicitFp = $data['fingerprint'] ?? $request->input('fingerprint') ?? $request->input('visitor_id') ?? $request->header('X-Client-Fingerprint');
        if (is_string($explicitFp) && preg_match('/^[a-zA-Z0-9_\-\.:]{8,128}$/', trim($explicitFp))) {
            $fingerprint = strtolower(trim($explicitFp));
        } else {
            $fingerprint = hash('sha256', "{$clientIp}|{$userAgent}|{$acceptLanguage}");
        }

        return [
            'fingerprint' => $fingerprint,
            'clientIp' => $clientIp,
            'physicalIp' => $physicalIp,
            'userAgent' => $userAgent,
            'acceptLanguage' => $acceptLanguage,
        ];
    }

    /**
     * @return array{siteKey: string, physicalIpKey: string, clientIpKey: ?string, fingerprintKey: string, contactKey: ?string, hasExplicitClientIp: bool, hasExplicitFingerprint: bool}
     */
    public function resolveRateLimitKeys(Request $request): array
    {
        $token = $request->header('X-API-Token');
        $physicalIp = $request->ip() ?: '127.0.0.1';
        $siteKey = ($token && Str::isUuid((string) $token))
            ? 'token:'.hash('sha256', (string) $token)
            : 'ip:'.$physicalIp;

        $hasExplicitClientIp = $request->filled('client_ip') || $request->hasHeader('CF-Connecting-IP');
        $hasExplicitFingerprint = $request->filled('fingerprint') || $request->filled('visitor_id') || $request->hasHeader('X-Client-Fingerprint');

        $resolved = $this->resolve($request);
        $clientIp = $resolved['clientIp'];
        $fingerprint = $resolved['fingerprint'];

        $phone = $request->input('client_phone') ?: $request->input('phone');
        $digits = $phone ? preg_replace('/\D/', '', (string) $phone) : null;
        $email = $request->input('client_email') ?: $request->input('email');
        $contactKey = $digits ? 'phone:'.$digits : ($email ? 'email:'.strtolower(trim((string) $email)) : null);

        return [
            'siteKey' => $siteKey,
            'physicalIpKey' => 'ip:'.$physicalIp,
            'clientIpKey' => $clientIp !== $physicalIp ? 'client_ip:'.$clientIp : null,
            'fingerprintKey' => 'fp:'.$fingerprint,
            'contactKey' => $contactKey ? 'contact:'.$contactKey : null,
            'hasExplicitClientIp' => $hasExplicitClientIp,
            'hasExplicitFingerprint' => $hasExplicitFingerprint,
        ];
    }
}
