<?php

declare(strict_types=1);

return [
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'free_year_days' => (int) env('BILLING_FREE_YEAR_DAYS', 365),
    'owner_lifetime_discount_email' => env('BILLING_OWNER_LIFETIME_DISCOUNT_EMAIL', 'develop@graysonerhard.com'),
    'owner_default_plan' => env('BILLING_OWNER_DEFAULT_PLAN', 'pro_yearly'),

    /*
    |--------------------------------------------------------------------------
    | Earnings-Based Billing Thresholds
    |--------------------------------------------------------------------------
    */
    'activation_threshold_cents' => (int) env('BILLING_ACTIVATION_THRESHOLD_CENTS', 20000),
    'grace_period_days' => (int) env('BILLING_GRACE_PERIOD_DAYS', 14),
    'yearly_nudge_threshold_cents' => (int) env('BILLING_YEARLY_NUDGE_THRESHOLD_CENTS', 60000),
    'veteran_monthly_threshold_cents' => (int) env('BILLING_VETERAN_MONTHLY_THRESHOLD_CENTS', 250000),
    'pro_monthly_price_cents' => 1999,
    'pro_yearly_price_cents' => 19999,
    'veteran_monthly_price_cents' => 4999,
];
