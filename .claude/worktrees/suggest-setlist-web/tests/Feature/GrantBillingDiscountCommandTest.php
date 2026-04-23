<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\BillingActivationService;

it('grants a complimentary free-year discount and optional plan via artisan', function () {
    $user = User::factory()->create([
        'email' => 'discounted@example.com',
        'billing_status' => User::BILLING_STATUS_SETUP_REQUIRED,
        'billing_plan' => null,
    ]);

    $this->artisan('billing:grant-discount', [
        'email' => $user->email,
        'discount' => User::BILLING_DISCOUNT_FREE_YEAR,
        '--plan' => User::BILLING_PLAN_PRO_YEARLY,
    ])->assertExitCode(0);

    $user->refresh();

    expect($user->billing_discount_type)->toBe(User::BILLING_DISCOUNT_FREE_YEAR);
    expect($user->billing_plan)->toBe(User::BILLING_PLAN_PRO_YEARLY);
    expect($user->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED);
    expect($user->billing_discount_ends_at)->not->toBeNull();
});

it('fails when discount type is invalid', function () {
    $this->artisan('billing:grant-discount', [
        'email' => 'test@example.com',
        'discount' => 'bogus_discount',
    ])
        ->expectsOutput('Discount must be one of: '.implode(', ', User::billingDiscountTypes()).'.')
        ->assertExitCode(1);
});

it('fails when plan is invalid', function () {
    $this->artisan('billing:grant-discount', [
        'email' => 'test@example.com',
        'discount' => User::BILLING_DISCOUNT_FREE_YEAR,
        '--plan' => 'invalid_plan',
    ])
        ->expectsOutput('Plan must be one of: '.implode(', ', User::billingPlans()).'.')
        ->assertExitCode(1);
});

it('fails when user is not found', function () {
    $this->artisan('billing:grant-discount', [
        'email' => 'nonexistent@example.com',
        'discount' => User::BILLING_DISCOUNT_FREE_YEAR,
    ])
        ->expectsOutput('No user found for nonexistent@example.com.')
        ->assertExitCode(1);
});

it('grants discount without plan when user has no billing plan selected', function () {
    $user = User::factory()->create([
        'email' => 'noplan@example.com',
        'billing_plan' => null,
        'billing_status' => User::BILLING_STATUS_SETUP_REQUIRED,
    ]);

    $this->artisan('billing:grant-discount', [
        'email' => $user->email,
        'discount' => User::BILLING_DISCOUNT_LIFETIME,
    ])
        ->expectsOutput("Granted lifetime discount to {$user->email}.")
        ->expectsOutput('No billing plan is selected yet. The user will still need to choose Basic or Pro before access is unlocked.')
        ->assertExitCode(0);

    $user->refresh();

    expect($user->billing_discount_type)->toBe(User::BILLING_DISCOUNT_LIFETIME);
    expect($user->billing_status)->toBe(User::BILLING_STATUS_SETUP_REQUIRED);
    expect($user->billing_discount_ends_at)->toBeNull();
});

it('fails when billingActivationService throws a RuntimeException', function () {
    $user = User::factory()->create([
        'email' => 'runtime@example.com',
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_SETUP_REQUIRED,
    ]);

    $service = mock(BillingActivationService::class);
    $service->shouldReceive('grantComplimentaryAccess')
        ->once()
        ->andThrow(new RuntimeException('Stripe API error'));

    app()->instance(BillingActivationService::class, $service);

    $this->artisan('billing:grant-discount', [
        'email' => $user->email,
        'discount' => User::BILLING_DISCOUNT_FREE_YEAR,
    ])
        ->expectsOutput('Stripe API error')
        ->assertExitCode(1);
});
