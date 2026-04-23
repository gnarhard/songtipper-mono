<?php

declare(strict_types=1);

use App\Mail\EarningsThresholdReachedMail;
use App\Models\Project;
use App\Models\Request;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config(['billing.activation_threshold_cents' => 20000]);
});

it('backfills cumulative tips and transitions over-threshold users to grace period', function () {
    $user = User::factory()->create([
        'billing_plan' => null,
        'billing_status' => User::BILLING_STATUS_SETUP_REQUIRED,
        'billing_total_paid_tips_cents' => 0,
    ]);

    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    Request::factory()->count(2)->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 16000,
        'payment_provider' => 'stripe',
    ]);

    $this->artisan('billing:migrate-to-earnings')->assertExitCode(0);

    $user->refresh();

    expect($user->billing_total_paid_tips_cents)->toBe(32000);
    expect($user->billing_plan)->toBe(User::BILLING_PLAN_FREE);
    expect($user->billing_status)->toBe(User::BILLING_STATUS_GRACE_PERIOD);
    expect($user->billing_grace_period_started_at)->not->toBeNull();

    Mail::assertQueued(EarningsThresholdReachedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

it('leaves under-threshold users in the earning state after backfill', function () {
    $user = User::factory()->create([
        'billing_plan' => null,
        'billing_status' => User::BILLING_STATUS_SETUP_REQUIRED,
    ]);

    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    Request::factory()->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 5000,
        'payment_provider' => 'stripe',
    ]);

    $this->artisan('billing:migrate-to-earnings')->assertExitCode(0);

    $user->refresh();

    expect($user->billing_total_paid_tips_cents)->toBe(5000);
    expect($user->billing_plan)->toBe(User::BILLING_PLAN_FREE);
    expect($user->billing_status)->toBe(User::BILLING_STATUS_EARNING);

    Mail::assertNotQueued(EarningsThresholdReachedMail::class);
});

it('leaves discounted users untouched during evaluation', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
    ]);

    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    Request::factory()->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 50000,
        'payment_provider' => 'stripe',
    ]);

    $this->artisan('billing:migrate-to-earnings')->assertExitCode(0);

    $user->refresh();

    // Backfill still writes the total (the backfill query is provider-scoped,
    // not plan-scoped) but the plan/status must not have moved.
    expect($user->billing_total_paid_tips_cents)->toBe(50000);
    expect($user->billing_plan)->toBe(User::BILLING_PLAN_PRO_YEARLY);
    expect($user->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED);

    Mail::assertNotQueued(EarningsThresholdReachedMail::class);
});

it('dry run does not mutate users or queue mail', function () {
    $user = User::factory()->create([
        'billing_plan' => null,
        'billing_status' => User::BILLING_STATUS_SETUP_REQUIRED,
        'billing_total_paid_tips_cents' => 0,
    ]);

    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    Request::factory()->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 40000,
        'payment_provider' => 'stripe',
    ]);

    $this->artisan('billing:migrate-to-earnings', ['--dry-run' => true])->assertExitCode(0);

    $user->refresh();

    expect($user->billing_total_paid_tips_cents)->toBe(0);
    expect($user->billing_plan)->toBeNull();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_SETUP_REQUIRED);

    Mail::assertNotQueued(EarningsThresholdReachedMail::class);
});

it('ignores non-stripe request rows when backfilling', function () {
    $user = User::factory()->create([
        'billing_plan' => null,
        'billing_status' => User::BILLING_STATUS_SETUP_REQUIRED,
    ]);

    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    Request::factory()->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 30000,
        'payment_provider' => 'cash',
    ]);

    $this->artisan('billing:migrate-to-earnings')->assertExitCode(0);

    $user->refresh();

    // Non-stripe rows are skipped by the backfill query, so this user has no
    // cumulative tips and should stay in the earning state.
    expect($user->billing_total_paid_tips_cents)->toBe(0);
    expect($user->billing_plan)->toBe(User::BILLING_PLAN_FREE);
    expect($user->billing_status)->toBe(User::BILLING_STATUS_EARNING);
});
