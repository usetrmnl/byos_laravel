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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'trmnl' => [
        'proxy_base_url' => env('TRMNL_PROXY_BASE_URL', 'https://trmnl.app'),
        'proxy_refresh_minutes' => env('TRMNL_PROXY_REFRESH_MINUTES', 15),
        'proxy_refresh_cron' => env('TRMNL_PROXY_REFRESH_CRON'),
        'override_orig_icon' => env('TRMNL_OVERRIDE_ORIG_ICON', false),
        'image_url_timeout' => env('TRMNL_IMAGE_URL_TIMEOUT', null),
    ],
    
];
