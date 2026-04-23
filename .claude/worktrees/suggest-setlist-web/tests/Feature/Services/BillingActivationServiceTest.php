<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\BillingActivationService;

it('marks discounted access', function () {
    $user = User::factory()->create();

    $service = app(BillingActivationService::class);
    $service->markDiscountedAccess($user, User::BILLING_PLAN_PRO_YEARLY);

    $user->refresh();
    expect($user->billing_plan)->toBe(User::BILLING_PLAN_PRO_YEARLY)
        ->and($user->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED);
});

it('grants complimentary access with lifetime discount', function () {
    $user = User::factory()->create();

    $service = app(BillingActivationService::class);
    $service->grantComplimentaryAccess(
        user: $user,
        billingPlan: User::BILLING_PLAN_PRO_YEARLY,
        discountType: User::BILLING_DISCOUNT_LIFETIME,
        discountEndsAt: null,
    );

    $user->refresh();
    expect($user->billing_plan)->toBe(User::BILLING_PLAN_PRO_YEARLY)
        ->and($user->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED)
        ->and($user->billing_discount_type)->toBe(User::BILLING_DISCOUNT_LIFETIME)
        ->and($user->billing_discount_ends_at)->toBeNull();
});

it('grants complimentary access with free year discount', function () {
    $user = User::factory()->create();
    $endsAt = now()->addYear();

    $service = app(BillingActivationService::class);
    $service->grantComplimentaryAccess(
        user: $user,
        billingPlan: User::BILLING_PLAN_PRO_YEARLY,
        discountType: 'free_year',
        discountEndsAt: $endsAt,
    );

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED)
        ->and($user->billing_discount_type)->toBe('free_year')
        ->and($user->billing_discount_ends_at)->not->toBeNull();
});

it('throws for invalid billing plan on complimentary access', function () {
    $user = User::factory()->create();

    $service = app(BillingActivationService::class);
    $service->grantComplimentaryAccess(
        user: $user,
        billingPlan: 'invalid_plan',
        discountType: User::BILLING_DISCOUNT_LIFETIME,
        discountEndsAt: null,
    );
})->throws(RuntimeException::class, 'Invalid billing plan');

it('throws for invalid discount type on complimentary access', function () {
    $user = User::factory()->create();

    $service = app(BillingActivationService::class);
    $service->grantComplimentaryAccess(
        user: $user,
        billingPlan: User::BILLING_PLAN_PRO_YEARLY,
        discountType: 'invalid_discount',
        discountEndsAt: null,
    );
})->throws(RuntimeException::class, 'Invalid discount type');

it('does not re-apply complimentary access when values are identical', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
        'billing_discount_ends_at' => null,
        'billing_activated_at' => now()->subMonth(),
    ]);
    $originalActivatedAt = $user->billing_activated_at;

    $service = app(BillingActivationService::class);
    $service->grantComplimentaryAccess(
        user: $user,
        billingPlan: User::BILLING_PLAN_PRO_YEARLY,
        discountType: User::BILLING_DISCOUNT_LIFETIME,
        discountEndsAt: null,
    );

    $user->refresh();
    // billing_activated_at should remain unchanged (no update needed)
    expect($user->billing_activated_at->toDateTimeString())
        ->toBe($originalActivatedAt->toDateTimeString());
});

it('marks invoice paid by stripe customer id', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_PAYMENT_FAILED,
        'billing_last_error_code' => 'card_declined',
    ]);

    $service = app(BillingActivationService::class);
    $service->markInvoicePaidByStripeCustomerId('cus_test_123');

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_ACTIVE)
        ->and($user->billing_last_error_code)->toBeNull();
});

it('skips invoice paid when user has active discount', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_discounted',
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
    ]);

    $service = app(BillingActivationService::class);
    $service->markInvoicePaidByStripeCustomerId('cus_discounted');

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED);
});

it('returns when stripe customer id is empty for invoice paid', function () {
    $service = app(BillingActivationService::class);
    $service->markInvoicePaidByStripeCustomerId('');

    // No exception = success
    expect(true)->toBeTrue();
});

it('marks invoice payment failed', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_failed',
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    $service = app(BillingActivationService::class);
    $service->markInvoicePaymentFailedByStripeCustomerId(
        'cus_failed',
        'card_declined',
        'Your card was declined.',
    );

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_PAYMENT_FAILED)
        ->and($user->billing_last_error_code)->toBe('card_declined');
});

it('syncs subscription status to trialing', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_trial',
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    $service = app(BillingActivationService::class);
    $service->syncSubscriptionStatusByStripeCustomerId('cus_trial', 'trialing');

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_TRIALING);
});

it('syncs subscription status to active', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_active',
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_TRIALING,
    ]);

    $service = app(BillingActivationService::class);
    $service->syncSubscriptionStatusByStripeCustomerId('cus_active', 'active');

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_ACTIVE);
});

it('syncs subscription status to payment failed for past_due', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_past_due',
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    $service = app(BillingActivationService::class);
    $service->syncSubscriptionStatusByStripeCustomerId('cus_past_due', 'past_due');

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_PAYMENT_FAILED);
});

it('syncs subscription status to payment failed for canceled', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_canceled',
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    $service = app(BillingActivationService::class);
    $service->syncSubscriptionStatusByStripeCustomerId('cus_canceled', 'canceled');

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_PAYMENT_FAILED)
        ->and($user->billing_last_error_code)->toBe('subscription_canceled');
});

it('skips sync for user without valid billing plan', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_noplan',
        'billing_plan' => null,
        'billing_status' => User::BILLING_STATUS_SETUP_REQUIRED,
    ]);

    $service = app(BillingActivationService::class);
    $service->syncSubscriptionStatusByStripeCustomerId('cus_noplan', 'active');

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_SETUP_REQUIRED);
});
