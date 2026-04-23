<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\PayoutAccountService;
use App\Services\StripeConnectAccountGateway;
use App\Services\StripePaymentMethodDomainRegistrar;
use Stripe\Account;

beforeEach(function () {
    $this->gateway = mock(StripeConnectAccountGateway::class);
    $this->domainRegistrar = mock(StripePaymentMethodDomainRegistrar::class);
    $this->domainRegistrar->shouldReceive('ensureConnectedAccountDomainRegistered')->andReturnNull();
});

it('returns null when user has no payout account', function () {
    $user = billingReadyUser();

    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);
    $result = $service->getForUser($user);

    expect($result)->toBeNull();
});

it('returns existing payout account without refresh', function () {
    $user = billingReadyUser();
    $payoutAccount = UserPayoutAccount::factory()->create([
        'user_id' => $user->id,
        'stripe_account_id' => 'acct_test_123',
    ]);

    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);
    $result = $service->getForUser($user, refreshFromStripe: false);

    expect($result->id)->toBe($payoutAccount->id);
});

it('refreshes account from stripe when requested', function () {
    $user = billingReadyUser();
    $payoutAccount = UserPayoutAccount::factory()->create([
        'user_id' => $user->id,
        'stripe_account_id' => 'acct_refresh_123',
    ]);

    $this->gateway->shouldReceive('retrieveAccount')
        ->once()
        ->with('acct_refresh_123')
        ->andReturn(Account::constructFrom([
            'id' => 'acct_refresh_123',
            'country' => 'US',
            'default_currency' => 'usd',
            'details_submitted' => true,
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'requirements' => [
                'currently_due' => [],
                'past_due' => [],
                'disabled_reason' => null,
            ],
        ]));

    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);
    $result = $service->getForUser($user, refreshFromStripe: true);

    expect($result->status)->toBe(UserPayoutAccount::STATUS_ENABLED)
        ->and($result->charges_enabled)->toBeTrue();
});

it('creates stripe express account for user without one', function () {
    $user = billingReadyUser();

    $this->gateway->shouldReceive('createExpressAccount')
        ->once()
        ->andReturn(Account::constructFrom([
            'id' => 'acct_new_123',
            'country' => 'US',
            'default_currency' => 'usd',
            'details_submitted' => false,
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'requirements' => [
                'currently_due' => ['individual.first_name'],
                'past_due' => [],
                'disabled_reason' => null,
            ],
        ]));

    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);
    $result = $service->ensureForUser($user);

    expect($result->stripe_account_id)->toBe('acct_new_123')
        ->and($result->status)->toBe(UserPayoutAccount::STATUS_PENDING);
});

it('returns existing payout account for user with one', function () {
    $user = billingReadyUser();
    $existing = UserPayoutAccount::factory()->create([
        'user_id' => $user->id,
        'stripe_account_id' => 'acct_existing_123',
    ]);

    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);
    $result = $service->ensureForUser($user);

    expect($result->id)->toBe($existing->id);
});

it('syncs from account updated event', function () {
    $user = billingReadyUser();
    $payoutAccount = UserPayoutAccount::factory()->create([
        'user_id' => $user->id,
        'stripe_account_id' => 'acct_sync_123',
    ]);

    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);
    $result = $service->syncFromAccountUpdatedEvent([
        'id' => 'acct_sync_123',
        'country' => 'US',
        'default_currency' => 'usd',
        'details_submitted' => true,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'requirements' => [
            'currently_due' => [],
            'past_due' => [],
            'disabled_reason' => null,
        ],
    ]);

    expect($result)->not->toBeNull()
        ->and($result->status)->toBe(UserPayoutAccount::STATUS_ENABLED);
});

it('returns null for sync with empty account id', function () {
    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);

    $result = $service->syncFromAccountUpdatedEvent(['id' => '']);

    expect($result)->toBeNull();
});

it('returns null for sync with unknown account', function () {
    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);

    $result = $service->syncFromAccountUpdatedEvent(['id' => 'acct_unknown']);

    expect($result)->toBeNull();
});

it('sets restricted status when past due requirements exist', function () {
    $user = billingReadyUser();
    $payoutAccount = UserPayoutAccount::factory()->create([
        'user_id' => $user->id,
        'stripe_account_id' => 'acct_restricted',
    ]);

    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);
    $result = $service->syncFromAccountUpdatedEvent([
        'id' => 'acct_restricted',
        'country' => 'US',
        'default_currency' => 'usd',
        'details_submitted' => true,
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'requirements' => [
            'currently_due' => [],
            'past_due' => ['external_account'],
            'disabled_reason' => 'requirements.past_due',
        ],
    ]);

    expect($result->status)->toBe(UserPayoutAccount::STATUS_RESTRICTED);
});

it('sets pending status for pending verification', function () {
    $user = billingReadyUser();
    $payoutAccount = UserPayoutAccount::factory()->create([
        'user_id' => $user->id,
        'stripe_account_id' => 'acct_pending_verify',
    ]);

    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);
    $result = $service->syncFromAccountUpdatedEvent([
        'id' => 'acct_pending_verify',
        'country' => 'US',
        'default_currency' => 'usd',
        'details_submitted' => true,
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'requirements' => [
            'currently_due' => [],
            'past_due' => [],
            'disabled_reason' => 'requirements.pending_verification',
        ],
    ]);

    expect($result->status)->toBe(UserPayoutAccount::STATUS_PENDING);
});

it('keeps requests enabled for any user on sync since all users are pro-tier', function () {
    $user = billingReadyUser(['billing_plan' => User::BILLING_PLAN_PRO_MONTHLY]);
    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
        'is_accepting_requests' => true,
    ]);

    $payoutAccount = UserPayoutAccount::factory()->create([
        'user_id' => $user->id,
        'stripe_account_id' => 'acct_basic_user',
    ]);

    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);
    $service->syncFromAccountUpdatedEvent([
        'id' => 'acct_basic_user',
        'country' => 'US',
        'default_currency' => 'usd',
        'details_submitted' => true,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'requirements' => [
            'currently_due' => [],
            'past_due' => [],
            'disabled_reason' => null,
        ],
    ]);

    $project->refresh();
    expect($project->is_accepting_requests)->toBeTrue();
});

it('enables requests when account becomes enabled for pro user', function () {
    $user = billingReadyUser(['billing_plan' => User::BILLING_PLAN_PRO_MONTHLY]);
    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
        'is_accepting_tips' => true,
        'is_accepting_requests' => false,
    ]);

    $payoutAccount = UserPayoutAccount::factory()->create([
        'user_id' => $user->id,
        'stripe_account_id' => 'acct_pro_enable',
        'status' => UserPayoutAccount::STATUS_PENDING,
    ]);

    $this->gateway->shouldReceive('retrieveAccount')
        ->once()
        ->with('acct_pro_enable')
        ->andReturn(Account::constructFrom([
            'id' => 'acct_pro_enable',
            'country' => 'US',
            'default_currency' => 'usd',
            'details_submitted' => true,
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'requirements' => [
                'currently_due' => [],
                'past_due' => [],
                'disabled_reason' => null,
            ],
        ]));

    $service = new PayoutAccountService($this->gateway, $this->domainRegistrar);
    $service->refreshAccount($payoutAccount);

    $project->refresh();
    expect($project->is_accepting_requests)->toBeTrue();
});
