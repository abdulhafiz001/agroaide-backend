<?php

return [
    // Coolify reverse proxies overwrite Host. Strict allowlisting breaks mobile
    // login whenever APP_URL does not exactly match the public hostname.
    // Set ENFORCE_TRUSTED_HOSTS=true and TRUSTED_HOSTS=your.domain.com to lock down.
    'enforce_trusted_hosts' => (bool) env('ENFORCE_TRUSTED_HOSTS', false),
    'trusted_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_HOSTS', '')),
    ))),
    'token_expiration_minutes' => 60 * 24 * 30,
    'max_request_bytes' => 12 * 1024 * 1024,
    'media' => [
        'image_max_bytes' => 8 * 1024 * 1024,
        'image_max_pixels' => 24_000_000,
        'audio_max_bytes' => 10 * 1024 * 1024,
    ],
    // Calendar-day caps per farmer (uses app timezone, default Africa/Lagos).
    'daily_limits' => [
        'scans' => (int) env('DAILY_SCAN_LIMIT', 4),
        'chat_messages' => (int) env('DAILY_CHAT_LIMIT', 8),
    ],
    'rate_limits' => [
        'register' => [5, 60],
        'login' => [10, 1],
        'recovery' => [5, 60],
        // Burst protection (per minute). Hard daily caps are enforced separately.
        'chat' => [8, 1],
        'scan' => [4, 1],
        'transcription' => [10, 1],
        'sync' => [30, 1],
        'export' => [3, 60],
        'feedback' => [10, 60],
        'staff-login' => [5, 15],
    ],
    'sync' => [
        'max_actions' => 100,
        'max_payload_kb' => 64,
    ],
    'retention_days' => [
        'exports' => 1,
        'otps' => 1,
        'sync_payload_logs' => 30,
        'conversations' => 365,
        'notifications' => 180,
        'temp_media' => 1,
    ],
];
