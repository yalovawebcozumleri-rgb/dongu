<?php

return [
    'placements' => [
        'home_feed' => ['first_after' => 3, 'repeat_every' => 8, 'max_per_session' => 5, 'min_items' => 3],
        'leaderboard' => ['first_after' => 10, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 10],
        'listing_detail' => ['first_after' => 0, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 0],
    ],
    'admob' => [
        'mode' => env('ADMOB_MODE', 'test'),
        'modes' => [
            'android' => env('ADMOB_ANDROID_MODE', env('ADMOB_MODE', 'test')),
            'ios' => env('ADMOB_IOS_MODE', env('ADMOB_MODE', 'test')),
        ],
        'test_unit_ids' => [
            'android' => [
                'native' => 'ca-app-pub-3940256099942544/2247696110',
                'interstitial' => 'ca-app-pub-3940256099942544/1033173712',
                'rewarded' => 'ca-app-pub-3940256099942544/5224354917',
            ],
            'ios' => [
                'native' => 'ca-app-pub-3940256099942544/3986624511',
                'interstitial' => 'ca-app-pub-3940256099942544/4411468910',
                'rewarded' => 'ca-app-pub-3940256099942544/1712485313',
            ],
        ],
    ],
    'impression_retention_days' => (int) env('AD_IMPRESSION_RETENTION_DAYS', 90),
];