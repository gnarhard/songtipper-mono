<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\PayoutAccountService;
use Mockery\MockInterface;

it('refreshes payout account status when onboarding returns to the dashboard', function () {
    $user = billingReadyUser();
    $payoutAccount = UserPayoutAccount::factory()->create([
        'user_id' => $user->id,
        'status' => UserPayoutAccount::STATUS_PENDING,
    ]);

    $this->mock(PayoutAccountService::class, function (MockInterface $mock) use ($user, $payoutAccount): void {
        $mock->shouldReceive('getForUser')
            ->once()
            ->withArgs(fn (User $requestedUser, bool $refreshFromStripe): bool => $requestedUser->id === $user->id && $refreshFromStripe)
            ->andReturn($payoutAccount);
    });

    $response = $this
        ->actingAs($user)
        ->get(route('payout-account.onboarding.return'));

    $response->assertRedirect(route('dashboard', [
        'payout_onboarding' => 'returned',
    ]));
});

it('redirects to stripe onboarding link when refreshing payout account', function () {
    $user = billingReadyUser();

    $this->mock(PayoutAccountService::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('createOnboardingLink')
            ->once()
            ->withArgs(fn (User $requestedUser): bool => $requestedUser->id === $user->id)
            ->andReturn('https://connect.stripe.com/onboarding/test123');
    });

    $response = $this
        ->actingAs($user)
        ->get(route('payout-account.onboarding.refresh'));

    $response->assertRedirect('https://connect.stripe.com/onboarding/test123');
});

it('still redirects to dashboard when payout refresh fails on onboarding return', function () {
    $user = billingReadyUser();

    $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('getForUser')
            ->once()
            ->andThrow(new RuntimeException('Stripe unavailable'));
    });

    $response = $this
        ->actingAs($user)
        ->get(route('payout-account.onboarding.return'));

    $response->assertRedirect(route('dashboard', [
        'payout_onboarding' => 'returned',
    ]));
});
