<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\PayoutAccountService;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('returns not started payout status when user has no payout account', function () {
    $response = $this->getJson('/api/v1/me/payout-account');

    $response->assertSuccessful()
        ->assertJsonPath('payout_account.status', 'not_started')
        ->assertJsonPath('payout_account.setup_complete', false)
        ->assertJsonPath('payout_account.stripe_account_id', null);
});

it('returns persisted payout account status', function () {
    UserPayoutAccount::query()->create([
        'user_id' => $this->user->id,
        'stripe_account_id' => 'acct_test_user_1',
        'status' => UserPayoutAccount::STATUS_ENABLED,
        'status_reason' => null,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);

    $response = $this->getJson('/api/v1/me/payout-account');

    $response->assertSuccessful()
        ->assertJsonPath('payout_account.status', UserPayoutAccount::STATUS_ENABLED)
        ->assertJsonPath('payout_account.setup_complete', true)
        ->assertJsonPath('payout_account.stripe_account_id', 'acct_test_user_1');
});

it('refreshes payout status from stripe when refresh flag is true', function () {
    $payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->user->id,
        'stripe_account_id' => 'acct_test_user_refresh',
        'status' => UserPayoutAccount::STATUS_PENDING,
        'status_reason' => 'capabilities_pending',
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);

    $this->mock(PayoutAccountService::class, function (MockInterface $mock) use ($payoutAccount): void {
        $mock->shouldReceive('getForUser')
            ->once()
            ->withArgs(fn (User $user, bool $refreshFromStripe): bool => $user->id === $this->user->id && $refreshFromStripe)
            ->andReturn($payoutAccount);
    });

    $response = $this->getJson('/api/v1/me/payout-account?refresh_from_stripe=1');

    $response->assertSuccessful()
        ->assertJsonPath('payout_account.status', UserPayoutAccount::STATUS_PENDING)
        ->assertJsonPath('payout_account.stripe_account_id', 'acct_test_user_refresh');
});

it('returns an onboarding link', function () {
    $payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->user->id,
        'stripe_account_id' => 'acct_test_user_2',
        'status' => UserPayoutAccount::STATUS_PENDING,
        'status_reason' => 'requirements_due',
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'details_submitted' => false,
        'requirements_currently_due' => ['external_account'],
        'requirements_past_due' => [],
    ]);

    $this->mock(PayoutAccountService::class, function (MockInterface $mock) use ($payoutAccount) {
        $mock->shouldReceive('ensureForUser')
            ->once()
            ->andReturn($payoutAccount);
        $mock->shouldReceive('createOnboardingLink')
            ->once()
            ->andReturn('https://example.com/onboarding-link');
    });

    $response = $this->postJson('/api/v1/me/payout-account/onboarding-link');

    $response->assertSuccessful()
        ->assertJsonPath('url', 'https://example.com/onboarding-link')
        ->assertJsonPath('link_type', 'onboarding')
        ->assertJsonPath('payout_account.stripe_account_id', 'acct_test_user_2');
});

it('returns onboarding link when payout account is not fully enabled', function () {
    $payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->user->id,
        'stripe_account_id' => 'acct_test_user_3',
        'status' => UserPayoutAccount::STATUS_PENDING,
        'status_reason' => 'requirements_due',
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'details_submitted' => false,
        'requirements_currently_due' => ['external_account'],
        'requirements_past_due' => [],
    ]);

    $this->mock(PayoutAccountService::class, function (MockInterface $mock) use ($payoutAccount) {
        $mock->shouldReceive('ensureForUser')
            ->once()
            ->andReturn($payoutAccount);
        $mock->shouldReceive('refreshAccount')
            ->once()
            ->withArgs(fn (UserPayoutAccount $account): bool => $account->id === $payoutAccount->id)
            ->andReturn($payoutAccount);
        $mock->shouldReceive('createOnboardingLink')
            ->once()
            ->andReturn('https://example.com/onboarding-link');
        $mock->shouldNotReceive('createDashboardLoginLink');
    });

    $response = $this->postJson('/api/v1/me/payout-account/dashboard-link');

    $response->assertSuccessful()
        ->assertJsonPath('url', 'https://example.com/onboarding-link')
        ->assertJsonPath('link_type', 'onboarding')
        ->assertJsonPath('payout_account.status', UserPayoutAccount::STATUS_PENDING);
});

it('returns a dashboard login link when payout account is enabled', function () {
    $payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->user->id,
        'stripe_account_id' => 'acct_test_user_4',
        'status' => UserPayoutAccount::STATUS_ENABLED,
        'status_reason' => null,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);

    $this->mock(PayoutAccountService::class, function (MockInterface $mock) use ($payoutAccount) {
        $mock->shouldReceive('ensureForUser')
            ->once()
            ->andReturn($payoutAccount);
        $mock->shouldReceive('refreshAccount')
            ->once()
            ->withArgs(fn (UserPayoutAccount $account): bool => $account->id === $payoutAccount->id)
            ->andReturn($payoutAccount);
        $mock->shouldReceive('createDashboardLoginLink')
            ->once()
            ->andReturn('https://example.com/dashboard-link');
        $mock->shouldNotReceive('createOnboardingLink');
    });

    $response = $this->postJson('/api/v1/me/payout-account/dashboard-link');

    $response->assertSuccessful()
        ->assertJsonPath('url', 'https://example.com/dashboard-link')
        ->assertJsonPath('link_type', 'dashboard')
        ->assertJsonPath('payout_account.status', UserPayoutAccount::STATUS_ENABLED);
});
