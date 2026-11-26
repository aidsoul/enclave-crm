<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ExternalLinkController extends Controller
{
    public function viber(Request $request)
    {
        $phone = (string) $request->query('phone', '');
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);

        if (! empty($cleanPhone) && ! str_starts_with($cleanPhone, '+')) {
            $cleanPhone = '+'.$cleanPhone;
        }

        $targetUrl = 'viber://chat?number='.urlencode($cleanPhone);

        return response()->view('tma.external.viber', [
            'phone' => $cleanPhone,
            'targetUrl' => $targetUrl,
        ]);
    }

    public function email(Request $request)
    {
        $to = trim((string) $request->query('to', ''));
        $cleanEmail = filter_var($to, FILTER_SANITIZE_EMAIL);
        $isValid = filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) !== false;
        $safeEmail = $isValid ? $cleanEmail : '';

        $mailtoUrl = $isValid ? 'mailto:'.$safeEmail : '#';
        $gmailUrl = $isValid ? 'https://mail.google.com/mail/?view=cm&fs=1&to='.urlencode($safeEmail) : '#';

        return response()->view('tma.external.email', [
            'email' => $safeEmail ?: $cleanEmail,
            'mailtoUrl' => $mailtoUrl,
            'gmailUrl' => $gmailUrl,
        ]);
    }

    public function telegram(Request $request)
    {
        $id = (string) $request->query('id', '');
        $username = (string) $request->query('username', '');
        $cleanUsername = preg_replace('/[^a-zA-Z0-9_]/', '', ltrim(trim($username), '@'));
        $cleanId = preg_replace('/[^\d]/', '', $id);

        if (! empty($cleanUsername)) {
            $targetUrl = 'https://t.me/'.urlencode($cleanUsername);
        } else {
            $targetUrl = 'tg://user?id='.$cleanId;
        }

        return response()->view('tma.external.telegram', [
            'id' => $cleanId,
            'username' => $cleanUsername,
            'targetUrl' => $targetUrl,
        ]);
    }
}
