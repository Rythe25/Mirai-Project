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

];
