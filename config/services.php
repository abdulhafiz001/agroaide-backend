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

    'plantnet' => [
        'api_key' => env('PLANTNET_API_KEY'),
        'endpoint' => 'https://my-api.plantnet.org/v2',
    ],

    // Crop disease identification — Kindwise crop.health (backend only).
    'kindwise' => [
        'api_key' => env('KINDWISE_API_KEY'),
        'base_url' => rtrim((string) env('KINDWISE_API_URL', 'https://crop.kindwise.com/api/v1'), '/'),
    ],

    // Primary AI — Google AI Studio / Gemini (backend only).
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'text_model' => env('GEMINI_TEXT_MODEL', 'gemini-2.0-flash'),
        'vision_model' => env('GEMINI_VISION_MODEL', 'gemini-2.0-flash'),
    ],

    // Optional legacy NVIDIA NIM fallback.
    'nvidia' => [
        'api_key' => env('NVIDIA_API_KEY'),
        'chat_endpoint' => 'https://integrate.api.nvidia.com/v1/chat/completions',
        'text_model' => env('NVIDIA_TEXT_MODEL', 'meta/llama-3.3-70b-instruct'),
        'vision_model' => env('NVIDIA_VISION_MODEL', 'meta/llama-3.2-11b-vision-instruct'),
    ],

    // Whisper transcription (+ optional chat fallback).
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'chat_endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
        'text_model' => env('GROQ_TEXT_MODEL', 'qwen/qwen3.6-27b'),
        'vision_model' => env('GROQ_VISION_MODEL', 'qwen/qwen3.6-27b'),
        'transcription_endpoint' => 'https://api.groq.com/openai/v1/audio/transcriptions',
        'transcription_model' => 'whisper-large-v3',
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        // Absolute paths (e.g. /run/secrets/firebase.json) are used as-is for Coolify mounts.
        // Relative paths are resolved from the app base path.
        'credentials' => ($fcmPath = env('FCM_CREDENTIALS_PATH', 'firebase-service-account.json'))
            ? (str_starts_with($fcmPath, '/') ? $fcmPath : base_path($fcmPath))
            : base_path('firebase-service-account.json'),
        // Coolify-friendly alternatives when the JSON file is not mounted into the image:
        // paste the full service-account JSON, or base64-encode the file contents.
        'credentials_json' => env('FCM_CREDENTIALS_JSON'),
        'credentials_base64' => env('FCM_CREDENTIALS_BASE64'),
    ],

    'marketeye' => [
        'api_key' => env('MARKETEYE_API_KEY'),
        'base_url' => env('MARKETEYE_BASE_URL', 'https://marketeye.ahzcode.sbs/api/v1/public'),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'folder' => env('CLOUDINARY_FOLDER', 'agroaide/uploads'),
    ],

];
