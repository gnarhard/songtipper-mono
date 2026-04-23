<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Mail::fake();
    config(['billing.activation_threshold_cents' => 20000]);
    config(['billing.grace_period_days' => 14]);
});

// --- Status ---

it('returns billing status for authenticated user', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
        'billing_total_paid_tips_cents' => 15000,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me/billing');

    $response->assertOk()
        ->assertJsonPath('data.billing_plan', 'free')
        ->assertJsonPath('data.billing_status', 'earning')
        ->assertJsonPath('data.cumulative_tip_cents', 15000)
        ->assertJsonPath('data.activation_threshold_cents', 20000)
        ->assertJsonPath('data.is_threshold_reached', false);
});

it('returns threshold_reached true when tips meet the threshold', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
        'billing_total_paid_tips_cents' => 20000,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me/billing');

    $response->assertOk()
        ->assertJsonPath('data.is_threshold_reached', true);
});

it('returns grace period days remaining', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_GRACE_PERIOD,
        'billing_grace_period_started_at' => now()->subDays(5),
        'billing_total_paid_tips_cents' => 25000,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me/billing');

    $response->assertOk()
        ->assertJsonPath('data.grace_period_days_remaining', 9);
});

it('returns null grace period days when not in grace period', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me/billing');

    $response->assertOk()
        ->assertJsonPath('data.grace_period_days_remaining', null);
});

it('requires authentication', function () {
    $this->getJson('/api/v1/me/billing')
        ->assertUnauthorized();
});

// --- Activate ---

it('rejects activation when user is not in grace period or card needed state', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/me/billing/activate', [
        'payment_method_id' => 'pm_test_123',
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Subscription activation is not required at this time.');
});

it('validates activate request fields', function () {
    $user = User::factory()->create([
        'billing_status' => User::BILLING_STATUS_GRACE_PERIOD,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/me/billing/activate', []);

    $response->assertUnprocessable();
});

it('rejects invalid billing plan for activation', function () {
    $user = User::factory()->create([
        'billing_status' => User::BILLING_STATUS_GRACE_PERIOD,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/me/billing/activate', [
        'payment_method_id' => 'pm_test_123',
        'billing_plan' => 'invalid_plan',
    ]);

    $response->assertUnprocessable();
});
