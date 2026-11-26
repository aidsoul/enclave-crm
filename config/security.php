<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Session IP Verification & Token Theft Protection
    |--------------------------------------------------------------------------
    |
    | When enabled, user sessions are bound to verified IP addresses. If an
    | unverified IP connects with an existing token, access is gated and a
    | confirmation key is sent to the user's Telegram chat with the bot.
    |
    */
    'ip_verification_enabled' => env('SECURITY_IP_VERIFICATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | User Sessions Limits
    |--------------------------------------------------------------------------
    |
    | Limits for concurrent active sessions per user.
    | TMA (Telegram Mini App) and Desktop (Browser) sessions are tracked separately,
    | ensuring frequent TMA mobile opens never evict active desktop sessions.
    |
    */
    'max_user_sessions' => (int) env('SECURITY_MAX_USER_SESSIONS', 10),
    'max_desktop_sessions' => (int) env('SECURITY_MAX_DESKTOP_SESSIONS', 5),
    'max_tma_sessions' => (int) env('SECURITY_MAX_TMA_SESSIONS', 3),

    /*
    |--------------------------------------------------------------------------
    | Temporary Block Duration (seconds)
    |--------------------------------------------------------------------------
    |
    | How long an IP is blocked when an invalid confirmation token/key is entered
    | repeatedly (default: 15 minutes = 900 seconds).
    |
    */
    'temp_block_duration' => (int) env('SECURITY_TEMP_BLOCK_DURATION', 900),

    /*
    |--------------------------------------------------------------------------
    | Manual Bot Block Duration (seconds)
    |--------------------------------------------------------------------------
    |
    | How long an IP is blocked when a user clicks "Block" in Telegram bot
    | (default: 1 hour = 3600 seconds).
    |
    */
    'bot_block_duration' => (int) env('SECURITY_BOT_BLOCK_DURATION', 3600),

    /*
    |--------------------------------------------------------------------------
    | Maximum Failed Confirmation Attempts
    |--------------------------------------------------------------------------
    |
    | Number of failed confirmation key attempts allowed before the IP is
    | temporarily blocked.
    |
    */
    'max_failed_attempts' => (int) env('SECURITY_MAX_FAILED_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Confirmation Code TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long a confirmation key remains valid (default: 10 minutes = 600 seconds).
    |
    */
    'confirm_code_ttl' => (int) env('SECURITY_CONFIRM_CODE_TTL', 600),

    /*
    |--------------------------------------------------------------------------
    | Telegram Alert Throttle (seconds)
    |--------------------------------------------------------------------------
    |
    | Minimum interval between alerts sent to the bot for the same unverified IP.
    |
    */
    'alert_throttle_ttl' => (int) env('SECURITY_ALERT_THROTTLE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Cloud Password Settings
    |--------------------------------------------------------------------------
    |
    | Minimum and maximum length of user cloud password used for 2FA verification.
    |
    */
    'cloud_password_min_length' => (int) env('SECURITY_CLOUD_PASSWORD_MIN_LENGTH', 4),
    'cloud_password_max_length' => (int) env('SECURITY_CLOUD_PASSWORD_MAX_LENGTH', 64),

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxy IPs / CIDRs
    |--------------------------------------------------------------------------
    */
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'))
    ))),
];
