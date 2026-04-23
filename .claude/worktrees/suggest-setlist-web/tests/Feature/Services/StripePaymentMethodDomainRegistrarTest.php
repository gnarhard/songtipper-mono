<?php

declare(strict_types=1);

use App\Services\StripePaymentMethodDomainGateway;
use App\Services\StripePaymentMethodDomainRegistrar;
use Illuminate\Support\Facades\Cache;
use Stripe\PaymentMethodDomain;

it('skips registration for empty stripe account id', function () {
    $gateway = mock(StripePaymentMethodDomainGateway::class);
    $gateway->shouldNotReceive('findByDomainName');
    $gateway->shouldNotReceive('create');

    $registrar = new StripePaymentMethodDomainRegistrar($gateway);
    $registrar->ensureConnectedAccountDomainRegistered('');

    // No exception = success
    expect(true)->toBeTrue();
});

it('skips registration when domain is already cached', function () {
    config()->set('services.stripe.payment_method_domain', 'example.com');

    $gateway = mock(StripePaymentMethodDomainGateway::class);
    $gateway->shouldNotReceive('findByDomainName');
    $gateway->shouldNotReceive('create');

    // Pre-cache the domain
    Cache::forever('stripe:payment_method_domain:platform:example.com', true);

    $registrar = new StripePaymentMethodDomainRegistrar($gateway);
    $registrar->ensurePlatformDomainRegistered();

    expect(true)->toBeTrue();
});

it('skips registration for localhost domain', function () {
    config()->set('services.stripe.payment_method_domain', '');
    config()->set('app.url', 'http://localhost');

    $gateway = mock(StripePaymentMethodDomainGateway::class);
    $gateway->shouldNotReceive('findByDomainName');

    $registrar = new StripePaymentMethodDomainRegistrar($gateway);
    $registrar->ensurePlatformDomainRegistered();

    expect(true)->toBeTrue();
});

it('skips registration for .test domain', function () {
    config()->set('services.stripe.payment_method_domain', '');
    config()->set('app.url', 'http://myapp.test');

    $gateway = mock(StripePaymentMethodDomainGateway::class);
    $gateway->shouldNotReceive('findByDomainName');

    $registrar = new StripePaymentMethodDomainRegistrar($gateway);
    $registrar->ensurePlatformDomainRegistered();

    expect(true)->toBeTrue();
});

it('skips registration for IP address domains', function () {
    config()->set('services.stripe.payment_method_domain', '');
    config()->set('app.url', 'http://192.168.1.1');

    $gateway = mock(StripePaymentMethodDomainGateway::class);
    $gateway->shouldNotReceive('findByDomainName');

    $registrar = new StripePaymentMethodDomainRegistrar($gateway);
    $registrar->ensurePlatformDomainRegistered();

    expect(true)->toBeTrue();
});

it('enables domain when found but not enabled', function () {
    config()->set('services.stripe.payment_method_domain', 'songtipper.com');
    Cache::flush();

    $existingDomain = PaymentMethodDomain::constructFrom([
        'id' => 'pmd_123',
        'enabled' => false,
        'domain_name' => 'songtipper.com',
    ]);
    $enabledDomain = PaymentMethodDomain::constructFrom([
        'id' => 'pmd_123',
        'enabled' => true,
        'domain_name' => 'songtipper.com',
    ]);

    $gateway = mock(StripePaymentMethodDomainGateway::class);
    $gateway->shouldReceive('findByDomainName')
        ->once()
        ->with('songtipper.com', null)
        ->andReturn($existingDomain);
    $gateway->shouldReceive('enable')
        ->once()
        ->with('pmd_123', null)
        ->andReturn($enabledDomain);
    $gateway->shouldReceive('validate')
        ->once()
        ->with('pmd_123', null);

    $registrar = new StripePaymentMethodDomainRegistrar($gateway);
    $registrar->ensurePlatformDomainRegistered();

    expect(Cache::has('stripe:payment_method_domain:platform:songtipper.com'))->toBeTrue();
});

it('creates domain when not found', function () {
    config()->set('services.stripe.payment_method_domain', 'newdomain.com');
    Cache::flush();

    $createdDomain = PaymentMethodDomain::constructFrom([
        'id' => 'pmd_456',
        'enabled' => true,
        'domain_name' => 'newdomain.com',
    ]);

    $gateway = mock(StripePaymentMethodDomainGateway::class);
    $gateway->shouldReceive('findByDomainName')
        ->once()
        ->with('newdomain.com', null)
        ->andReturnNull();
    $gateway->shouldReceive('create')
        ->once()
        ->with('newdomain.com', null)
        ->andReturn($createdDomain);
    $gateway->shouldReceive('validate')
        ->once()
        ->with('pmd_456', null);

    $registrar = new StripePaymentMethodDomainRegistrar($gateway);
    $registrar->ensurePlatformDomainRegistered();

    expect(Cache::has('stripe:payment_method_domain:platform:newdomain.com'))->toBeTrue();
});
