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

        // Alerts sent to the iPhone app during a cook.
        'alerts' => [
            'pit_deviation_percent' => env('CYBERQ_PIT_ALERT_PERCENT', 20),
            'pit_alert_after_minutes' => env('CYBERQ_PIT_ALERT_AFTER_MINUTES', 5),
            'pit_reminder_after_minutes' => env('CYBERQ_PIT_REMINDER_AFTER_MINUTES', 15),
            'food_nearly_ready_minutes' => env('CYBERQ_FOOD_NEARLY_READY_MINUTES', 60),
            'offline_after_missed_polls' => env('CYBERQ_OFFLINE_AFTER_MISSED_POLLS', 5),
            'offline_reminder_after_minutes' => env('CYBERQ_OFFLINE_REMINDER_AFTER_MINUTES', 30),
        ],
    ],

    // Apple Push Notification service, for the iPhone app's Live Activities.
    'apns' => [
        'key_id' => env('APNS_KEY_ID'),
        'team_id' => env('APNS_TEAM_ID'),
        'private_key_path' => env('APNS_PRIVATE_KEY_PATH'),
        'bundle_id' => env('APNS_BUNDLE_ID', 'net.lrhosting.cyberq'),
    ],

];
