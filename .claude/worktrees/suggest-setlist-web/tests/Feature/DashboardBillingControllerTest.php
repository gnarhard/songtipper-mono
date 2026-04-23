<?php

declare(strict_types=1);

use App\Mail\EarningsThresholdReachedMail;
use App\Models\User;
use App\Services\BillingActivationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ViewErrorBag;

it('renders billing page for all authenticated users regardless of billing status', function () {
    $user = setupRequiredUser();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();
});

it('renders billing page for users with completed setup', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_TRIALING,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();
    $response->assertSee('Subscription Overview');
    $response->assertSee('Change Plan');
});

it('injects the livewire script bundle on the billing page so alpine-powered nav dropdowns work', function () {
    // Regression: the billing page has no Livewire component, so nothing
    // was injecting Livewire's bundled Alpine runtime. That left
    // layouts/navigation.blade.php's x-data user menu dropdown inert.
    // The shared layout must always emit @livewireScripts.
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();
    $response->assertSee('livewire.js', false);
});

it('configures payment element theme using the user color scheme preference', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_TRIALING,
    ]);

    $response = $this
        ->actingAs($user)
        ->view('dashboard.billing', [
            'user' => $user,
            'planGroups' => [],
            'planLabel' => 'Pro Monthly',
            'planPriceLabel' => '$19.99/mo',
            'stripePublishableKey' => 'pk_test_dashboard_billing',
            'setupIntentClientSecret' => 'seti_dashboard_billing_secret',
            'setupIntentError' => null,
            'billingStatusLabel' => 'Free trial',
            'canOpenPortal' => true,
            'canUpdatePaymentMethod' => true,
            'discountLabel' => null,
            'discountEndsAt' => null,
            'needsPaymentSetup' => false,
            'isActiveSubscriber' => true,
            'cumulativeTipCents' => 50000,
            'activationThresholdCents' => 20000,
            'progressPercent' => 100,
            'gracePeriodDaysRemaining' => null,
            'isTopEarner' => false,
            'isVerifiedEarner' => false,
            'errors' => new ViewErrorBag,
        ]);

    $response->assertSee("window.matchMedia('(prefers-color-scheme: dark)')", false);
    $response->assertSee("theme: prefersDarkMode ? 'night' : 'stripe'", false);
});

it('allows plan changes for paid users', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    $billingActivationService = Mockery::mock(BillingActivationService::class);
    $billingActivationService->shouldReceive('swapPlan')
        ->once()
        ->withArgs(fn (User $boundUser, string $billingPlan): bool => $boundUser->is($user) && $billingPlan === User::BILLING_PLAN_PRO_YEARLY);
    $this->app->instance(BillingActivationService::class, $billingActivationService);

    $response = $this
        ->actingAs($user)
        ->post(route('dashboard.billing.plan'), [
            'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        ]);

    $response->assertRedirect(route('dashboard.billing.show'));
});

it('allows plan changes for complimentary users without Stripe swap', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
    ]);

    $billingActivationService = Mockery::mock(BillingActivationService::class);
    $billingActivationService->shouldReceive('markDiscountedAccess')
        ->once()
        ->withArgs(fn (User $boundUser, string $billingPlan): bool => $boundUser->is($user) && $billingPlan === User::BILLING_PLAN_PRO_YEARLY);
    $this->app->instance(BillingActivationService::class, $billingActivationService);

    $response = $this
        ->actingAs($user)
        ->post(route('dashboard.billing.plan'), [
            'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        ]);

    $response->assertRedirect(route('dashboard.billing.show'));
});

it('validates payment method update payload for paid users', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('dashboard.billing.payment-method'), []);

    $response->assertSessionHasErrors('payment_method_id');
});

it('rejects payment method updates for complimentary users', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('dashboard.billing.payment-method'), [
            'payment_method_id' => 'pm_should_not_be_used',
        ]);

    $response->assertRedirect(route('dashboard.billing.show'));
    $response->assertSessionHasErrors('billing');
});

it('blocks the Stripe billing portal for complimentary users', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.portal'));

    $response->assertRedirect(route('dashboard.billing.show'));
    $response->assertSessionHasErrors('portal');
});

it('redirects with error when plan swap throws exception', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    $billingActivationService = Mockery::mock(BillingActivationService::class);
    $billingActivationService->shouldReceive('swapPlan')
        ->once()
        ->andThrow(new RuntimeException('Stripe swap error'));
    $this->app->instance(BillingActivationService::class, $billingActivationService);

    $response = $this
        ->actingAs($user)
        ->post(route('dashboard.billing.plan'), [
            'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        ]);

    $response->assertRedirect(route('dashboard.billing.show'));
    $response->assertSessionHasErrors('billing');
});

it('shows lifetime complimentary access status label', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();
    $response->assertSee('Lifetime complimentary access');
});

it('shows complimentary access status label for non-lifetime discount', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
        'billing_discount_type' => User::BILLING_DISCOUNT_FREE_YEAR,
        'billing_discount_ends_at' => now()->addYear(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();
    $response->assertSee('Complimentary access');
});

it('shows payment failed status label', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_PAYMENT_FAILED,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();
    $response->assertSee('Payment failed');
});

it('shows active subscription status label', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();
    $response->assertSee('Active subscription');
});

it('self-heals free earning users whose cumulative tips already cross the activation threshold', function () {
    Mail::fake();
    config(['billing.activation_threshold_cents' => 20000]);

    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
        'billing_total_paid_tips_cents' => 32300,
        'billing_grace_period_started_at' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();
    $response->assertSee('Subscription required');

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_GRACE_PERIOD);
    expect($user->billing_grace_period_started_at)->not->toBeNull();

    Mail::assertQueued(EarningsThresholdReachedMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});

it('renders grace-period activation form with both plan options selectable via peer-checked styling', function () {
    Mail::fake();
    config(['billing.activation_threshold_cents' => 20000]);

    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_GRACE_PERIOD,
        'billing_grace_period_started_at' => now(),
        'billing_total_paid_tips_cents' => 32300,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();

    // Both plan radios must be present with distinct IDs so clicking either one updates
    // the visual state via Tailwind's peer-checked utilities (pure CSS, no JS).
    $response->assertSee('id="billing-activation-plan-'.User::BILLING_PLAN_PRO_MONTHLY.'"', false);
    $response->assertSee('id="billing-activation-plan-'.User::BILLING_PLAN_PRO_YEARLY.'"', false);
    $response->assertSee('peer sr-only', false);
    $response->assertSee('peer-checked:border-brand-600', false);

    // Yearly is the default-checked plan (it carries the "Recommended" badge).
    $response->assertSee('value="'.User::BILLING_PLAN_PRO_YEARLY.'" class="peer sr-only" checked', false);

    // The "After $200 earned" interval label is redundant once a user is in grace period,
    // since by definition they've already crossed the threshold.
    $response->assertDontSee('After $200 earned');
});

it('hides the "After $200 earned" interval label in the active-subscriber change-plan form', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();
    $response->assertSee('Change Plan');
    $response->assertDontSee('After $200 earned');
});

it('does not evaluate the threshold for free earning users under the threshold', function () {
    Mail::fake();
    config(['billing.activation_threshold_cents' => 20000]);

    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
        'billing_total_paid_tips_cents' => 15000,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();

    $user->refresh();
    expect($user->billing_status)->toBe(User::BILLING_STATUS_EARNING);

    Mail::assertNotQueued(EarningsThresholdReachedMail::class);
});
