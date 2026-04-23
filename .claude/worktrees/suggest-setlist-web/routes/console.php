<?php

declare(strict_types=1);

use App\Jobs\PollBatchResults;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('account-usage:monitor')
    ->dailyAt('02:00')
    ->timezone('America/Denver');

Schedule::command('backup:run')
    ->dailyAt('02:30')
    ->timezone('America/Denver');

Schedule::job(new PollBatchResults)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->environments(['production']);

Schedule::command('songs:ai-review')
    ->dailyAt('03:00')
    ->timezone('America/Denver')
    ->withoutOverlapping()
    ->environments(['production']);

Schedule::command('r2:cleanup-trash')
    ->dailyAt('04:00')
    ->timezone('America/Denver');

Schedule::command('billing:evaluate-monthly')
    ->monthlyOn(1, '05:00')
    ->timezone('America/Denver')
    ->withoutOverlapping()
    ->environments(['production']);

Schedule::command('billing:check-grace-period')
    ->dailyAt('05:00')
    ->timezone('America/Denver');

Schedule::command('account-usage:send-admin-digest')
    ->weeklyOn(
        (int) config('account_usage.weekly_digest_day_of_week', 1),
        (string) config('account_usage.weekly_digest_time', '08:00'),
    )
    ->timezone((string) config('account_usage.weekly_digest_timezone', 'America/Denver'));
