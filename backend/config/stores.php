<?php

return [
    'app_store_available' => filter_var(env('APP_STORE_AVAILABLE', false), FILTER_VALIDATE_BOOL),
    'app_store_url' => env(
        'APP_STORE_URL',
        'https://apps.apple.com/tr/app/id6800822946'
    ),
    'google_play_available' => filter_var(env('GOOGLE_PLAY_AVAILABLE', true), FILTER_VALIDATE_BOOL),
    'google_play_url' => env(
        'GOOGLE_PLAY_URL',
        'https://play.google.com/store/apps/details?id=com.yalovawebcozumleri.dongu'
    ),
];
