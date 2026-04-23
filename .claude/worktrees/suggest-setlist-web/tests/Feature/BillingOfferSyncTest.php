<?php

declare(strict_types=1);

use App\Models\BillingOffer;
use App\Models\User;

it('applies a stored complimentary billing offer during registration', function () {
    $offerEndsAt = now()->addDays((int) config('billing.free_year_days', 365))->startOfSecond();

    BillingOffer::factory()->create([
        'email' => 'gifted@example.com',
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_discount_type' => User::BILLING_DISCOUNT_FREE_YEAR,
        'billing_discount_ends_at' => $offerEndsAt,
    ]);

    $response = $this->post(route('register'), [
        'name' => 'Gifted Artist',
        'email' => 'gifted@example.com',
        'password' => 'TestPassword123',
        'password_confirmation' => 'TestPassword123',
        'instrument_type' => 'vocals',
    ]);

    $user = User::query()
        ->where('email', 'gifted@example.com')
        ->firstOrFail();

    $response->assertRedirect(route('verification.notice'));
    $this->assertAuthenticatedAs($user);

    expect($user->billing_plan)->toBe(User::BILLING_PLAN_PRO_YEARLY);
    expect($user->billing_discount_type)->toBe(User::BILLING_DISCOUNT_FREE_YEAR);
    expect($user->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED);
    expect($user->billing_discount_ends_at?->toDateTimeString())->toBe($offerEndsAt->toDateTimeString());
});
