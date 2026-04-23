<?php

declare(strict_types=1);

use App\Services\PaymentService;

it('can be instantiated', function () {
    config()->set('services.stripe.secret', 'sk_test_fake');

    $service = new PaymentService;

    expect($service)->toBeInstanceOf(PaymentService::class);
});
