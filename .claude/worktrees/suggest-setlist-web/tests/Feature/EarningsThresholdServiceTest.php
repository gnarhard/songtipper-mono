<?php

declare(strict_types=1);

use App\Mail\EarningsThresholdReachedMail;
use App\Mail\YearlyPlanNudgeMail;
use App\Models\User;
use App\Services\EarningsThresholdService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config(['billing.activation_threshold_cents' => 20000]);
    config(['billing.yearly_nudge_threshold_cents' => 60000]);
});

// --- Activation threshold ---

it('enters grace period when free user reaches activation threshold', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
        'billing_total_paid_tips_cents' => 20000,
    ]);

    app(EarningsThresholdService::class)->evaluate($user);

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_GRACE_PERIOD);
    expect($user->billing_grace_period_started_at)->not->toBeNull();

    Mail::assertQueued(EarningsThresholdReachedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

it('enters grace period when tips exceed the threshold', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
        'billing_total_paid_tips_cents' => 25000,
    ]);

    app(EarningsThresholdService::class)->evaluate($user);

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_GRACE_PERIOD);
});

it('does not activate when tips are below the threshold', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
        'billing_total_paid_tips_cents' => 19999,
    ]);

    app(EarningsThresholdService::class)->evaluate($user);

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_EARNING);

    Mail::assertNotQueued(EarningsThresholdReachedMail::class);
});

it('does not activate when user already has a billing plan', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
        'billing_total_paid_tips_cents' => 30000,
    ]);

    app(EarningsThresholdService::class)->evaluate($user);

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_ACTIVE);

    Mail::assertNotQueued(EarningsThresholdReachedMail::class);
});

it('skips evaluation entirely when user has an active billing discount', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
        'billing_discount_type' => 'lifetime',
        'billing_total_paid_tips_cents' => 50000,
    ]);

    app(EarningsThresholdService::class)->evaluate($user);

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED);

    Mail::assertNotQueued(EarningsThresholdReachedMail::class);
    Mail::assertNotQueued(YearlyPlanNudgeMail::class);
});

// --- Yearly nudge threshold ---

it('sends yearly nudge when monthly pro user exceeds nudge threshold', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
        'billing_total_paid_tips_cents' => 60000,
        'billing_yearly_nudge_sent_at' => null,
    ]);

    app(EarningsThresholdService::class)->evaluate($user);

    $user->refresh();
    expect($user->billing_yearly_nudge_sent_at)->not->toBeNull();

    Mail::assertQueued(YearlyPlanNudgeMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

it('does not send yearly nudge when already sent', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
        'billing_total_paid_tips_cents' => 80000,
        'billing_yearly_nudge_sent_at' => now()->subDay(),
    ]);

    app(EarningsThresholdService::class)->evaluate($user);

    Mail::assertNotQueued(YearlyPlanNudgeMail::class);
});

it('does not send yearly nudge to yearly plan users', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
        'billing_total_paid_tips_cents' => 80000,
        'billing_yearly_nudge_sent_at' => null,
    ]);

    app(EarningsThresholdService::class)->evaluate($user);

    Mail::assertNotQueued(YearlyPlanNudgeMail::class);
});

it('does not send yearly nudge when below nudge threshold', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
        'billing_total_paid_tips_cents' => 59999,
        'billing_yearly_nudge_sent_at' => null,
    ]);

    app(EarningsThresholdService::class)->evaluate($user);

    Mail::assertNotQueued(YearlyPlanNudgeMail::class);
});
