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

    'expo' => [
        'push_enabled' => (bool) env('EXPO_PUSH_ENABLED', false),
        'endpoint' => env('EXPO_PUSH_ENDPOINT', 'https://exp.host/--/api/v2/push/send'),
        'access_token' => env('EXPO_ACCESS_TOKEN'),
    ],

    'google_play_review' => [
        'enabled' => filter_var(env('GOOGLE_PLAY_REVIEW_ENABLED', false), FILTER_VALIDATE_BOOL),
        'email' => env('GOOGLE_PLAY_REVIEW_EMAIL'),
        'code_hash' => env('GOOGLE_PLAY_REVIEW_CODE_HASH'),
    ],

    'app_store_review' => [
        'enabled' => filter_var(env('APP_STORE_REVIEW_ENABLED', false), FILTER_VALIDATE_BOOL),
        'email' => env('APP_STORE_REVIEW_EMAIL'),
        'secondary_email' => env('APP_STORE_REVIEW_SECONDARY_EMAIL'),
        'code_hash' => env('APP_STORE_REVIEW_CODE_HASH'),
        'demo' => [
            'enabled' => filter_var(env('APP_STORE_REVIEW_DEMO_ENABLED', false), FILTER_VALIDATE_BOOL),
            'latitude' => (float) env('APP_STORE_REVIEW_DEMO_LATITUDE', 40.655),
            'longitude' => (float) env('APP_STORE_REVIEW_DEMO_LONGITUDE', 29.276),
            'radius' => (float) env('APP_STORE_REVIEW_DEMO_RADIUS', 50),
            'province' => env('APP_STORE_REVIEW_DEMO_PROVINCE', 'Yalova'),
            'public_area' => env('APP_STORE_REVIEW_DEMO_PUBLIC_AREA', 'Yalova Merkez'),
            'exact_address' => env('APP_STORE_REVIEW_DEMO_EXACT_ADDRESS', 'Yalova Merkez demo teslimat noktası'),
        ],
    ],

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

];
