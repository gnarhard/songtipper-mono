<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\PayoutAccountService;
use Livewire\Livewire;
use Mockery\MockInterface;

describe('Payout Setup Wallet Component', function () {
    it('is not rendered on the profile page', function () {
        $user = billingReadyUser();

        $this->actingAs($user)
            ->get('/profile')
            ->assertSuccessful()
            ->assertDontSee('Payout Setup and Wallet');
    });

    it('shows payout status not started when no payout account exists', function () {
        $user = billingReadyUser();

        Livewire::actingAs($user)
            ->test('payout-setup-wallet')
            ->assertSee('Not started')
            ->assertSee('Connect Stripe Express to collect tips and cash out.');
    });

    it('shows payout status enabled when setup is complete', function () {
        $user = billingReadyUser();
        UserPayoutAccount::factory()->create([
            'user_id' => $user->id,
            'status' => UserPayoutAccount::STATUS_ENABLED,
            'stripe_account_id' => 'acct_test_123',
        ]);

        Livewire::actingAs($user)
            ->test('payout-setup-wallet')
            ->assertSee('Ready for requests and payouts')
            ->assertSee('Update Setup');
    });

    it('shows stripe express setup required for pro users without payout setup', function () {
        $user = billingReadyUser([
            'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        ]);

        Livewire::actingAs($user)
            ->test('payout-setup-wallet')
            ->assertSee('Stripe Express Setup Required');
    });

    it('shows stripe express setup required for all users without payout setup', function () {
        $user = billingReadyUser([
            'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        ]);

        Livewire::actingAs($user)
            ->test('payout-setup-wallet')
            ->assertSee('Stripe Express Setup Required');
    });

    it('shows lifetime tips total across all projects', function () {
        $user = billingReadyUser();
        $project = Project::factory()->create(['owner_user_id' => $user->id]);

        SongRequest::factory()->create([
            'project_id' => $project->id,
            'payment_provider' => 'stripe',
            'tip_amount_cents' => 2500,
        ]);
        SongRequest::factory()->create([
            'project_id' => $project->id,
            'payment_provider' => 'stripe',
            'tip_amount_cents' => 1500,
        ]);

        Livewire::actingAs($user)
            ->test('payout-setup-wallet')
            ->assertSee('$40');
    });

    it('refreshes stripe connect status', function () {
        $user = billingReadyUser();
        UserPayoutAccount::factory()->create([
            'user_id' => $user->id,
            'status' => UserPayoutAccount::STATUS_PENDING,
            'status_reason' => 'requirements_due',
        ]);

        $this->mock(PayoutAccountService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('getForUser')
                ->once()
                ->withArgs(fn (User $requestedUser, bool $refreshFromStripe): bool => $requestedUser->id === $user->id && $refreshFromStripe)
                ->andReturnUsing(function () use ($user): UserPayoutAccount {
                    UserPayoutAccount::query()
                        ->where('user_id', $user->id)
                        ->update([
                            'status' => UserPayoutAccount::STATUS_RESTRICTED,
                            'status_reason' => 'requirements_past_due',
                        ]);

                    return UserPayoutAccount::query()
                        ->where('user_id', $user->id)
                        ->firstOrFail();
                });
        });

        Livewire::actingAs($user)
            ->test('payout-setup-wallet')
            ->assertSee('Setup in progress')
            ->call('refreshStripeConnectStatus')
            ->assertHasNoErrors(['payout'])
            ->assertSee('Action required in Stripe');
    });

    it('shows error when stripe status refresh fails', function () {
        $user = billingReadyUser();
        UserPayoutAccount::factory()->create([
            'user_id' => $user->id,
            'status' => UserPayoutAccount::STATUS_PENDING,
        ]);

        $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getForUser')
                ->once()
                ->andThrow(new RuntimeException('Stripe unavailable'));
        });

        Livewire::actingAs($user)
            ->test('payout-setup-wallet')
            ->call('refreshStripeConnectStatus')
            ->assertHasErrors(['payout'])
            ->assertSee('Unable to refresh Stripe status right now. Please try again.');
    });
});

describe('Dashboard Wallet Stats', function () {
    it('shows wallet stats near the top of the dashboard', function () {
        $user = billingReadyUser();
        $project = Project::factory()->create(['owner_user_id' => $user->id]);

        SongRequest::factory()->create([
            'project_id' => $project->id,
            'payment_provider' => 'stripe',
            'tip_amount_cents' => 5000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Available to Cash Out')
            ->assertSee('Pending Balance')
            ->assertSee('Lifetime Tips (All Projects)');
    });

    it('displays lifetime tips independent of any timeline', function () {
        $user = billingReadyUser();
        $project = Project::factory()->create(['owner_user_id' => $user->id]);

        SongRequest::factory()->create([
            'project_id' => $project->id,
            'payment_provider' => 'stripe',
            'tip_amount_cents' => 10000,
        ]);

        Livewire::actingAs($user)
            ->test('dashboard-page')
            ->assertSee('$100.00');
    });

    it('breaks lifetime tips down into gross, fees, and net from stripe settlement data', function () {
        $user = billingReadyUser();
        $project = Project::factory()->create(['owner_user_id' => $user->id]);

        SongRequest::factory()->create([
            'project_id' => $project->id,
            'payment_provider' => 'stripe',
            'tip_amount_cents' => 10000,
            'stripe_fee_amount_cents' => 350,
            'stripe_net_amount_cents' => 9650,
        ]);

        Livewire::actingAs($user)
            ->test('dashboard-page')
            ->assertSee('Lifetime Tips (All Projects)')
            ->assertSee('Stripe Fees')
            ->assertSee('Net Payout')
            ->assertSee('$100.00')
            ->assertSee('$3.50')
            ->assertSee('$96.50');
    });
});
