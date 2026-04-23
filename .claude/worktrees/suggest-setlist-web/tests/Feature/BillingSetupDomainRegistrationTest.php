<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\StripePaymentMethodDomainRegistrar;

it('redirects to dashboard from billing setup since setup is always complete', function () {
    $user = setupRequiredUser();

    $response = $this
        ->actingAs($user)
        ->get(route('setup.billing.show'));

    $response->assertRedirect(route('dashboard'));
});

it('ensures the platform payment method domain is registered before loading dashboard billing', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_TRIALING,
    ]);

    $paymentMethodDomainRegistrar = Mockery::mock(StripePaymentMethodDomainRegistrar::class);
    $paymentMethodDomainRegistrar->shouldReceive('ensurePlatformDomainRegistered')
        ->once();
    $this->app->instance(StripePaymentMethodDomainRegistrar::class, $paymentMethodDomainRegistrar);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard.billing.show'));

    $response->assertOk();
});
