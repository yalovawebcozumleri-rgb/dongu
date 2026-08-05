<?php

return [
    'placements' => [
        'home_feed' => ['first_after' => 3, 'repeat_every' => 8, 'max_per_session' => 1000, 'min_items' => 3],
        'leaderboard' => ['first_after' => 10, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 10],
        'listing_detail' => ['first_after' => 0, 'repeat_every' => 0, 'max_per_session' => 1, 'min_items' => 0],
    ],
    'admob' => [
        'mode' => env('ADMOB_MODE', 'test'),
    ],
    'impression_retention_days' => (int) env('AD_IMPRESSION_RETENTION_DAYS', 90),
];