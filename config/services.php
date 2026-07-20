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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paddle (merchant of record — SPEC §7.3)
    |--------------------------------------------------------------------------
    |
    | Cashier Paddle reads the same credentials from config/cashier.php; the
    | checkout pages consume the client-side token from here so the billing
    | config surface matches the other services.
    |
    */

    'paddle' => [
        'api_key' => env('PADDLE_API_KEY'),
        'client_side_token' => env('PADDLE_CLIENT_SIDE_TOKEN'),
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
        'sandbox' => env('PADDLE_SANDBOX', true),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
