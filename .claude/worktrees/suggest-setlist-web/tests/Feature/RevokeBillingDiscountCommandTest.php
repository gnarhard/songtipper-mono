<?php

declare(strict_types=1);

use App\Models\BillingOffer;
use App\Models\User;

beforeEach(function () {
    config()->set('billing.owner_lifetime_discount_email', '');
});

it('revokes a free-year discount and resets billing status', function () {
    $user = User::factory()->create([
        'email' => 'discounted@example.com',
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_discount_type' => User::BILLING_DISCOUNT_FREE_YEAR,
        'billing_discount_ends_at' => now()->addYear(),
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
    ]);

    $this->artisan('billing:revoke-discount', ['email' => $user->email])
        ->expectsOutput("Revoked billing discount for {$user->email}.")
        ->assertExitCode(0);

    $user->refresh();

    expect($user->billing_discount_type)->toBeNull();
    expect($user->billing_discount_ends_at)->toBeNull();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_SETUP_REQUIRED);
});

it('revokes a lifetime discount', function () {
    $user = User::factory()->create([
        'email' => 'lifer@example.com',
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
        'billing_discount_ends_at' => null,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
    ]);

    $this->artisan('billing:revoke-discount', ['email' => $user->email])
        ->assertExitCode(0);

    $user->refresh();

    expect($user->hasActiveBillingDiscount())->toBeFalse();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_SETUP_REQUIRED);
});

it('fails when user is not found', function () {
    $this->artisan('billing:revoke-discount', ['email' => 'nobody@example.com'])
        ->expectsOutput('No user found for nobody@example.com.')
        ->assertExitCode(1);
});

it('warns when user has no discount to revoke', function () {
    $user = User::factory()->create([
        'email' => 'plain@example.com',
        'billing_discount_type' => null,
        'billing_discount_ends_at' => null,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    $this->artisan('billing:revoke-discount', ['email' => $user->email])
        ->expectsOutput("{$user->email} has no billing discount to revoke.")
        ->assertExitCode(0);

    $user->refresh();

    expect($user->billing_status)->toBe(User::BILLING_STATUS_ACTIVE);
});

it('deletes billing offers when --delete-offer is set', function () {
    $user = User::factory()->create([
        'email' => 'offeruser@example.com',
        'billing_discount_type' => User::BILLING_DISCOUNT_FREE_YEAR,
        'billing_discount_ends_at' => now()->addYear(),
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
    ]);

    BillingOffer::factory()->create(['email' => $user->email]);

    $this->artisan('billing:revoke-discount', [
        'email' => $user->email,
        '--delete-offer' => true,
    ])
        ->expectsOutput("Deleted 1 billing offer record(s) for {$user->email}.")
        ->assertExitCode(0);

    expect(BillingOffer::query()->forEmail($user->email)->exists())->toBeFalse();
});

it('warns about lingering billing offer when --delete-offer is not set', function () {
    $user = User::factory()->create([
        'email' => 'lingering@example.com',
        'billing_discount_type' => User::BILLING_DISCOUNT_FREE_YEAR,
        'billing_discount_ends_at' => now()->addYear(),
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
    ]);

    BillingOffer::factory()->create(['email' => $user->email]);

    $this->artisan('billing:revoke-discount', ['email' => $user->email])
        ->expectsOutput('A BillingOffer record still exists for this email — re-run with --delete-offer to remove it, otherwise the offer link could re-grant access.')
        ->assertExitCode(0);

    expect(BillingOffer::query()->forEmail($user->email)->exists())->toBeTrue();
});

it('warns when revoking a user that matches the configured lifetime discount email', function () {
    config()->set('billing.owner_lifetime_discount_email', 'owner@example.com');

    $user = User::factory()->create([
        'email' => 'owner@example.com',
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
        'billing_discount_ends_at' => null,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
    ]);

    $this->artisan('billing:revoke-discount', ['email' => $user->email])
        ->expectsOutput('This email matches BILLING_OWNER_LIFETIME_DISCOUNT_EMAIL — the lifetime discount will be re-applied automatically on the next request. Update the env var to fully revoke.')
        ->assertExitCode(0);
});

it('normalizes email casing and whitespace', function () {
    $user = User::factory()->create([
        'email' => 'mixed@example.com',
        'billing_discount_type' => User::BILLING_DISCOUNT_FREE_YEAR,
        'billing_discount_ends_at' => now()->addYear(),
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
    ]);

    $this->artisan('billing:revoke-discount', ['email' => '  MIXED@EXAMPLE.COM  '])
        ->assertExitCode(0);

    $user->refresh();

    expect($user->billing_discount_type)->toBeNull();
});
