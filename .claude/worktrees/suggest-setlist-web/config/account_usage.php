<?php

declare(strict_types=1);

return [
    'weekly_digest_timezone' => env('ACCOUNT_USAGE_WEEKLY_DIGEST_TIMEZONE', 'America/Denver'),
    'weekly_digest_day_of_week' => (int) env('ACCOUNT_USAGE_WEEKLY_DIGEST_DAY', 1),
    'weekly_digest_time' => env('ACCOUNT_USAGE_WEEKLY_DIGEST_TIME', '08:00'),
    'inactivity_archive_after_days' => (int) env('ACCOUNT_USAGE_INACTIVITY_ARCHIVE_AFTER_DAYS', 365),
    'inactivity_warning_days_before_archive' => (int) env('ACCOUNT_USAGE_INACTIVITY_WARNING_DAYS', 30),
    'anomaly_window_days' => (int) env('ACCOUNT_USAGE_ANOMALY_WINDOW_DAYS', 14),
    'ai_cost_micros' => [
        'openai' => (int) env('ACCOUNT_USAGE_OPENAI_COST_MICROS', 1500),
        'anthropic' => (int) env('ACCOUNT_USAGE_ANTHROPIC_COST_MICROS', 2200),
        'anthropic_batch' => (int) env('ACCOUNT_USAGE_ANTHROPIC_BATCH_COST_MICROS', 1100),
        'anthropic_haiku' => (int) env('ACCOUNT_USAGE_ANTHROPIC_HAIKU_COST_MICROS', 220),
        'gemini' => (int) env('ACCOUNT_USAGE_GEMINI_COST_MICROS', 900),
        'unknown' => (int) env('ACCOUNT_USAGE_UNKNOWN_AI_COST_MICROS', 1200),
    ],
    /*
    |--------------------------------------------------------------------------
    | Universal Plan
    |--------------------------------------------------------------------------
    |
    | All users get the same feature set and limits regardless of billing plan.
    | The only gating is audience requesting, which is controlled by billing
    | status (not plan tier) via ProjectEntitlementService.
    |
    */
    'plans' => [
        'pro' => [
            'repertoire_song_limit' => null,
            'project_limit' => null,
            'single_chart_upload_limit_bytes' => 2 * 1024 * 1024,
            'bulk_chart_upload_limit_bytes' => 2 * 1024 * 1024,
            'bulk_chart_file_limit' => 20,
            'storage' => [
                'warning_bytes' => 40 * 1024 * 1024 * 1024,
                'review_bytes' => 50 * 1024 * 1024 * 1024,
                'block_bytes' => 75 * 1024 * 1024 * 1024,
            ],
            'ai' => [
                'warning_limits' => [4000, 5000],
                'block_limit' => 7500,
                'interactive_per_minute' => 30,
                'bulk_window_limit' => 500,
                'bulk_window_hours' => 6,
            ],
            'uploads' => [
                'per_minute' => 10,
            ],
            'features' => [
                'public_requests' => true,
                'queue' => true,
                'history' => true,
                'owner_stats' => true,
                'wallet' => true,
                'band_sync' => true,
            ],
        ],
    ],
];
