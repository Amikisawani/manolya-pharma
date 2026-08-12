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

    'ocr' => [
        'driver' => env('OCR_DRIVER', 'local'), // local|http
        'url' => env('OCR_HTTP_URL'),
        'timeout' => env('OCR_HTTP_TIMEOUT', 60),
    ],

    'sms' => [
        'enabled' => env('SMS_ENABLED', false),
        'driver' => env('SMS_DRIVER', 'log'), // log|orange|airtel|auto
        'auto_fallback' => env('SMS_AUTO_FALLBACK', 'orange'),
        'orange' => [
            'url' => env('SMS_ORANGE_URL'),
            'token' => env('SMS_ORANGE_TOKEN'),
            'sender' => env('SMS_ORANGE_SENDER', 'Manolya'),
            'timeout' => env('SMS_ORANGE_TIMEOUT', 30),
            'prefixes' => ['80', '81', '84', '85', '89'],
        ],
        'airtel' => [
            'url' => env('SMS_AIRTEL_URL'),
            'token' => env('SMS_AIRTEL_TOKEN'),
            'sender' => env('SMS_AIRTEL_SENDER', 'Manolya'),
            'timeout' => env('SMS_AIRTEL_TIMEOUT', 30),
            'prefixes' => ['97', '98', '99'],
        ],
    ],

    'momo' => [
        'default' => env('MOMO_DEFAULT_PROVIDER', 'stub'),
        'orange' => [
            'token' => env('MOMO_ORANGE_TOKEN'),
            'charge_url' => env('MOMO_ORANGE_CHARGE_URL'),
            'refund_url' => env('MOMO_ORANGE_REFUND_URL'),
            'timeout' => env('MOMO_ORANGE_TIMEOUT', 45),
        ],
        'airtel' => [
            'token' => env('MOMO_AIRTEL_TOKEN'),
            'charge_url' => env('MOMO_AIRTEL_CHARGE_URL'),
            'refund_url' => env('MOMO_AIRTEL_REFUND_URL'),
            'timeout' => env('MOMO_AIRTEL_TIMEOUT', 45),
        ],
        'mtn' => [
            'token' => env('MOMO_MTN_TOKEN'),
            'charge_url' => env('MOMO_MTN_CHARGE_URL'),
            'refund_url' => env('MOMO_MTN_REFUND_URL'),
            'timeout' => env('MOMO_MTN_TIMEOUT', 45),
        ],
    ],

];
