<?php

declare(strict_types=1);

use App\Models\User;

it('returns complimentary access label when discount has no end date', function () {
    // Covers line 213 — billing_discount_ends_at is null for a free_year discount
    $user = User::factory()->create([
        'billing_discount_type' => User::BILLING_DISCOUNT_FREE_YEAR,
        'billing_discount_ends_at' => now()->addYear(),
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
    ]);

    $label = $user->billingDiscountLabel();
    expect($label)->toContain('Complimentary access through');
});

it('returns complimentary access without date when ends_at is null for free year', function () {
    // This is a special case where the user has an active free_year but
    // billing_discount_ends_at is in the future
    $user = User::factory()->create([
        'billing_discount_type' => User::BILLING_DISCOUNT_FREE_YEAR,
        'billing_discount_ends_at' => null,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
    ]);

    // hasActiveBillingDiscount returns false when ends_at is null for free_year
    $label = $user->billingDiscountLabel();
    expect($label)->toBeNull();
});

it('does not sync lifetime discount when already synced', function () {
    // Covers line 245 — syncConfiguredLifetimeDiscount returns false
    // when attributes are already in sync
    config()->set('billing.owner_lifetime_discount_email', 'lifetime@example.com');

    $user = User::factory()->create([
        'email' => 'lifetime@example.com',
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
        'billing_discount_ends_at' => null,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
        'billing_activated_at' => now(),
        'billing_last_error_code' => null,
        'billing_last_error_message' => null,
    ]);

    // Already in the correct state, should return false
    $result = $user->syncConfiguredLifetimeDiscount();
    expect($result)->toBeFalse();
});
