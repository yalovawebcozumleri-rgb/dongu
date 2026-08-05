<?php

return [
    'listing_lifetime_days' => (int) env('LISTING_LIFETIME_DAYS', 30),
    'expired_listing_retention_days' => (int) env('EXPIRED_LISTING_RETENTION_DAYS', 90),
    'deleted_listing_retention_days' => (int) env('DELETED_LISTING_RETENTION_DAYS', 30),
    'review_window_hours' => (int) env('REVIEW_WINDOW_HOURS', 24),
    'max_cycle_points_per_delivery' => (int) env('MAX_CYCLE_POINTS_PER_DELIVERY', 500),
    'cycle_risk_review_score' => (int) env('CYCLE_RISK_REVIEW_SCORE', 30),
    'revoked_push_token_retention_days' => (int) env('REVOKED_PUSH_TOKEN_RETENTION_DAYS', 30),
    'stale_push_token_days' => (int) env('STALE_PUSH_TOKEN_DAYS', 120),
    'login_code_retention_days' => (int) env('LOGIN_CODE_RETENTION_DAYS', 7),
    'admin_session_retention_days' => (int) env('ADMIN_SESSION_RETENTION_DAYS', 30),
];