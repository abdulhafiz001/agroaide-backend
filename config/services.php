<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'github_models' => [
        'api_key' => env('GITHUB_MODELS_API_KEY'),
        'model' => 'openai/gpt-4o-mini',
        'endpoint' => 'https://models.github.ai/inference/chat/completions',
        'api_version' => '2022-11-28',
    ],

    'plantnet' => [
        'api_key' => env('PLANTNET_API_KEY'),
        'endpoint' => 'https://my-api.plantnet.org/v2',
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'endpoint' => 'https://api.groq.com/openai/v1/audio/transcriptions',
        'model' => 'whisper-large-v3',
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        // Absolute paths (e.g. /run/secrets/firebase.json) are used as-is for Coolify mounts.
        // Relative paths are resolved from the app base path.
        'credentials' => ($fcmPath = env('FCM_CREDENTIALS_PATH', 'firebase-service-account.json'))
            ? (str_starts_with($fcmPath, '/') ? $fcmPath : base_path($fcmPath))
            : base_path('firebase-service-account.json'),
    ],

];
