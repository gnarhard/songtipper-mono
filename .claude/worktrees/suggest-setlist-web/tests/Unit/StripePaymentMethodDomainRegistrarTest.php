<?php

declare(strict_types=1);

use App\Services\StripePaymentMethodDomainGateway;
use App\Services\StripePaymentMethodDomainRegistrar;
use Illuminate\Support\Facades\Cache;
use Stripe\PaymentMethodDomain;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('cache.default', 'array');
    config()->set('services.stripe.secret', 'sk_test_domain_registration');
    Cache::flush();
});

it('skips automatic registration for local-only app domains', function () {
    config()->set('app.url', 'https://songtipper.test');
    config()->set('services.stripe.payment_method_domain', null);

    $paymentMethodDomainGateway = Mockery::mock(StripePaymentMethodDomainGateway::class);
    $paymentMethodDomainGateway->shouldNotReceive('findByDomainName');
    $paymentMethodDomainGateway->shouldNotReceive('create');
    $paymentMethodDomainGateway->shouldNotReceive('enable');
    $paymentMethodDomainGateway->shouldNotReceive('validate');

    $registrar = new StripePaymentMethodDomainRegistrar($paymentMethodDomainGateway);
    $registrar->ensurePlatformDomainRegistered();

    expect(true)->toBeTrue();
});

it('registers and validates the configured connected-account payment method domain', function () {
    config()->set('app.url', 'https://songtipper.test');
    config()->set('services.stripe.payment_method_domain', 'songtipper.ngrok-free.app');

    $createdDomain = PaymentMethodDomain::constructFrom([
        'id' => 'pmd_connected_domain_test',
        'enabled' => true,
    ]);

    $paymentMethodDomainGateway = Mockery::mock(StripePaymentMethodDomainGateway::class);
    $paymentMethodDomainGateway->shouldReceive('findByDomainName')
        ->once()
        ->with('songtipper.ngrok-free.app', 'acct_domain_test')
        ->andReturn(null);
    $paymentMethodDomainGateway->shouldReceive('create')
        ->once()
        ->with('songtipper.ngrok-free.app', 'acct_domain_test')
        ->andReturn($createdDomain);
    $paymentMethodDomainGateway->shouldNotReceive('enable');
    $paymentMethodDomainGateway->shouldReceive('validate')
        ->once()
        ->with('pmd_connected_domain_test', 'acct_domain_test');

    $registrar = new StripePaymentMethodDomainRegistrar($paymentMethodDomainGateway);
    $registrar->ensureConnectedAccountDomainRegistered('acct_domain_test');
});
