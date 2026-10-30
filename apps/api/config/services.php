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

    // ABA PayWay. Set ABA_PAYWAY_BASE_URL=http://payway-mock:4010 to test without ABA.
    'payway' => [
        'base_url' => rtrim(env('ABA_PAYWAY_BASE_URL') ?: 'https://checkout-sandbox.payway.com.kh', '/'),
        'merchant_id' => env('ABA_PAYWAY_MERCHANT_ID'),
        'api_key' => env('ABA_PAYWAY_API_KEY'),
        'callback_url' => env('ABA_PAYWAY_CALLBACK_URL'), // optional; PayWay must whitelist the domain
        'lifetime' => (int) (env('ABA_PAYWAY_LIFETIME') ?: 6), // minutes, PayWay minimum is 3
        'env' => env('ABA_PAYWAY_ENV', 'sandbox'), // "sandbox" enables manual approval in the backoffice
    ],

    // Google sign-in for storefront customers. A relative redirect is built from the request host, so register both
    // http://localhost:8000/auth/google/callback and https://mirai-api.kandycode.com/auth/google/callback in Google Cloud.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => '/auth/google/callback',
    ],

    // Staff alerts. Token from @BotFather; chat ID of the orders group (a negative number).
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'orders_chat_id' => env('TELEGRAM_ORDERS_CHAT_ID'),
    ],

];
