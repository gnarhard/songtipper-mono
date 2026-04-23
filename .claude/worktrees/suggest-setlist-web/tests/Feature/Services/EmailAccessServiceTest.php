<?php

declare(strict_types=1);

use App\Mail\BillingOfferMail;
use App\Models\BillingOffer;
use App\Models\User;
use App\Services\EmailAccessService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

it('does not sync billing offer when user matches configured lifetime email', function () {
    $user = User::factory()->create(['email' => 'lifetime@example.com']);
    config()->set('billing.owner_lifetime_discount_email', 'lifetime@example.com');

    BillingOffer::factory()->create([
        'email' => $user->email,
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
        'billing_discount_ends_at' => null,
    ]);

    $service = app(EmailAccessService::class);
    $result = $service->syncBillingOfferForUser($user);

    $user->refresh();
    expect($result)->toBeTrue()
        ->and($user->billing_discount_type)->toBe(User::BILLING_DISCOUNT_LIFETIME);
});

it('does not sync expired non-lifetime billing offer', function () {
    $user = User::factory()->create();

    BillingOffer::factory()->create([
        'email' => $user->email,
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_discount_type' => 'free_year',
        'billing_discount_ends_at' => now()->subDay(),
    ]);

    $service = app(EmailAccessService::class);
    $result = $service->syncBillingOfferForUser($user);

    expect($result)->toBeFalse();
});

it('syncs valid non-lifetime billing offer with future end date', function () {
    config()->set('billing.owner_lifetime_discount_email', '');
    $user = User::factory()->create();

    BillingOffer::factory()->create([
        'email' => $user->email,
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_discount_type' => 'free_year',
        'billing_discount_ends_at' => now()->addYear(),
    ]);

    $service = app(EmailAccessService::class);
    $result = $service->syncBillingOfferForUser($user);

    expect($result)->toBeTrue();
    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED);
});

it('sends billing offer with free year discount', function () {
    config()->set('billing.owner_lifetime_discount_email', '');

    $service = app(EmailAccessService::class);
    $result = $service->sendBillingOffer(
        'test@example.com',
        User::BILLING_PLAN_PRO_YEARLY,
        'free_year',
    );

    expect($result->email)->toBe('test@example.com')
        ->and($result->billing_discount_type)->toBe('free_year')
        ->and($result->billing_discount_ends_at)->not->toBeNull()
        ->and($result->sent_at)->not->toBeNull();

    Mail::assertSent(BillingOfferMail::class);
});

it('sends billing offer with lifetime discount', function () {
    config()->set('billing.owner_lifetime_discount_email', '');

    $service = app(EmailAccessService::class);
    $result = $service->sendBillingOffer(
        'lifetime-test@example.com',
        User::BILLING_PLAN_PRO_YEARLY,
        User::BILLING_DISCOUNT_LIFETIME,
    );

    expect($result->billing_discount_type)->toBe(User::BILLING_DISCOUNT_LIFETIME)
        ->and($result->billing_discount_ends_at)->toBeNull();

    Mail::assertSent(BillingOfferMail::class);
});
