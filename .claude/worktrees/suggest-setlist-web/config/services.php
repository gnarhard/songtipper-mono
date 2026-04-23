<?php

declare(strict_types=1);

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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'payment_method_domain' => env('STRIPE_PAYMENT_METHOD_DOMAIN'),
        'connect_refresh_url' => env('STRIPE_CONNECT_REFRESH_URL'),
        'connect_return_url' => env('STRIPE_CONNECT_RETURN_URL'),
        'pro_monthly_price' => env('STRIPE_PRO_MONTHLY_PRICE'),
        'pro_yearly_price' => env('STRIPE_PRO_YEARLY_PRICE'),
        'veteran_monthly_price' => env('STRIPE_VETERAN_MONTHLY_PRICE'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'anthropic'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 45),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        'metadata_model' => env('ANTHROPIC_METADATA_MODEL', 'claude-haiku-4-5'),
        'timeout_seconds' => (int) env('ANTHROPIC_TIMEOUT_SECONDS', 45),
        'batch_threshold' => (int) env('ANTHROPIC_BATCH_THRESHOLD', 200),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
        'timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 45),
    ],

    'youtube' => [
        'api_key' => env('YOUTUBE_DATA_API_KEY'),
        'timeout_seconds' => (int) env('YOUTUBE_TIMEOUT_SECONDS', 5),
    ],

];
