<?php

return [
    'token_expiration_minutes' => 60 * 24 * 30,
    'max_request_bytes' => 12 * 1024 * 1024,
    'media' => [
        'image_max_bytes' => 8 * 1024 * 1024,
        'image_max_pixels' => 24_000_000,
        'audio_max_bytes' => 10 * 1024 * 1024,
    ],
    'rate_limits' => [
        'register' => [5, 60],
        'login' => [10, 1],
        'recovery' => [5, 60],
        'chat' => [20, 1],
        'scan' => [10, 1],
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
