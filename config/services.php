<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /*
    | MailChannels Email API. The `mailchannels/mailchannels-php` Laravel mail
    | driver reads `api_key` and `base_url`; the webhook endpoint uses
    | `base_url` and `webhook_public_key` to verify signatures.
    */
    'mailchannels' => [
        'api_key' => env('MAILCHANNELS_API_KEY'),
        'base_url' => env('MAILCHANNELS_API_BASE_URL') ?: 'https://api.mailchannels.net/tx/v1',
        // Optional: base64 SPKI DER, PEM, or raw 32-byte base64 Ed25519 key.
        // When set, it is used instead of fetching /webhook/public-key.
        'webhook_public_key' => env('MAILCHANNELS_WEBHOOK_PUBLIC_KEY'),
        'webhook_key_cache_seconds' => 3600,
        'webhook_max_age_seconds' => 300,
        'webhook_max_future_seconds' => 60,
    ],

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

];
