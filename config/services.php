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

    'cyberq' => [
        'timeout' => env('CYBERQ_TIMEOUT', 5),
        'abandoned_cook_hours' => env('CYBERQ_ABANDONED_COOK_HOURS', 12),
    ],

    'ios' => [
        // Team ID + bundle ID of the iPhone app, e.g. ABCDE12345.net.lrhosting.cyberq, so it may use this site's passkeys.
        'app_ids' => array_filter(explode(',', (string) env('IOS_APP_IDS', ''))),
    ],

];
