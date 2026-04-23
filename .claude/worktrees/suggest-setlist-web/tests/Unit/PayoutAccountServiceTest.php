<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\PayoutAccountService;
use App\Services\StripeConnectAccountGateway;
use App\Services\StripePaymentMethodDomainRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Account;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('registers the audience payment method domain when creating a payout account', function () {
    $stripeConnectAccountGateway = Mockery::mock(StripeConnectAccountGateway::class);
    $stripeConnectAccountGateway->shouldReceive('createExpressAccount')
        ->once()
        ->andReturn(Account::constructFrom([
            'id' => 'acct_domain_registration_test',
            'country' => 'US',
            'default_currency' => 'usd',
            'details_submitted' => false,
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'requirements' => [
                'currently_due' => [],
                'past_due' => [],
                'disabled_reason' => null,
            ],
        ]));

    $paymentMethodDomainRegistrar = Mockery::mock(StripePaymentMethodDomainRegistrar::class);
    $paymentMethodDomainRegistrar->shouldReceive('ensureConnectedAccountDomainRegistered')
        ->once()
        ->with('acct_domain_registration_test');

    $payoutAccountService = new PayoutAccountService(
        $stripeConnectAccountGateway,
        $paymentMethodDomainRegistrar,
    );
    $user = User::factory()->create();

    $payoutAccount = $payoutAccountService->ensureForUser($user);

    expect($payoutAccount->stripe_account_id)->toBe('acct_domain_registration_test');
});
