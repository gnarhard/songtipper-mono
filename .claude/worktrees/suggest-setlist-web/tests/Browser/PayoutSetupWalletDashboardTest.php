<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\PayoutAccountService;
use App\Services\PayoutWalletService;
use Mockery\MockInterface;

describe('Dashboard payout setup and wallet management', function () {
    it('starts payout setup from the dashboard', function () {
        $user = billingReadyUser([
            'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        ]);
        Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $this->actingAs($user);

        $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createOnboardingLink')
                ->once()
                ->andReturn(route('dashboard', [
                    'payout_onboarding' => 'started',
                ]));
        });

        $page = visit('/dashboard');

        $page->assertSee('Payout Setup and Wallet')
            ->assertSee('Stripe Express Setup Required')
            ->assertSee('Start Setup')
            ->click('[data-test="start-payout-setup"]')
            ->assertPathIs('/dashboard')
            ->assertQueryStringHas('payout_onboarding', 'started')
            ->assertNoJavaScriptErrors();
    });

    it('opens onboarding when payout setup is not complete', function () {
        $user = billingReadyUser();
        Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $this->actingAs($user);

        $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createOnboardingLink')
                ->once()
                ->andReturn(route('dashboard', [
                    'payout_onboarding' => 'continued',
                ]));
        });

        $page = visit('/dashboard');

        $page->assertSee('Open Stripe Express')
            ->click('[data-test="open-stripe-express"]')
            ->assertPathIs('/dashboard')
            ->assertQueryStringHas('payout_onboarding', 'continued')
            ->assertNoJavaScriptErrors();
    });

    it('refreshes stripe connect status from the dashboard', function () {
        $user = billingReadyUser();
        Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        UserPayoutAccount::factory()->create([
            'user_id' => $user->id,
            'status' => UserPayoutAccount::STATUS_PENDING,
            'status_reason' => 'requirements_due',
        ]);

        $this->actingAs($user);

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

        $page = visit('/dashboard');

        $page->assertSee('Refresh Status')
            ->assertSee('Setup in progress')
            ->click('[data-test="refresh-stripe-status"]')
            ->assertSee('Action required in Stripe')
            ->assertSee('Stripe has overdue requirements to resolve.')
            ->assertNoJavaScriptErrors();
    });

    it('shows wallet totals and opens stripe express when setup is complete', function () {
        $user = billingReadyUser();
        $project = Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        UserPayoutAccount::factory()->enabled()->create([
            'user_id' => $user->id,
            'stripe_account_id' => 'acct_dashboard_wallet_1',
        ]);

        SongRequest::factory()->create([
            'project_id' => $project->id,
            'payment_provider' => 'stripe',
            'tip_amount_cents' => 1234,
            'score_cents' => 1234,
        ]);
        SongRequest::factory()->create([
            'project_id' => $project->id,
            'payment_provider' => 'stripe',
            'tip_amount_cents' => 2222,
            'score_cents' => 2222,
        ]);
        SongRequest::factory()->create([
            'project_id' => $project->id,
            'payment_provider' => 'none',
            'tip_amount_cents' => 9999,
            'score_cents' => 9999,
        ]);

        $this->actingAs($user);

        $this->mock(PayoutWalletService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveBalance')
                ->atLeast()
                ->once()
                ->with('acct_dashboard_wallet_1')
                ->andReturn([
                    'available' => [['currency' => 'usd', 'amount_cents' => 4321]],
                    'pending' => [['currency' => 'usd', 'amount_cents' => 987]],
                    'available_total_cents' => 4321,
                    'pending_total_cents' => 987,
                    'retrieved_at' => now()->toIso8601String(),
                ]);
        });

        $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createDashboardLoginLink')
                ->once()
                ->andReturn(route('dashboard', [
                    'stripe_express' => 'opened',
                ]));
        });

        $page = visit('/dashboard');

        $page->assertSee('Payout Setup and Wallet')
            ->assertSee('$43.21')
            ->assertSee('$9.87')
            ->assertSee('$34.56')
            ->click('[data-test="open-stripe-express"]')
            ->assertPathIs('/dashboard')
            ->assertQueryStringHas('stripe_express', 'opened')
            ->assertNoJavaScriptErrors();
    });

    it('renders payout and wallet panel with readable text in dark mode', function () {
        $user = billingReadyUser();
        Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $this->actingAs($user);

        $page = visit('/dashboard')->inDarkMode();

        $page->assertSee('Payout Setup and Wallet')
            ->assertScript("window.matchMedia('(prefers-color-scheme: dark)').matches", true)
            ->assertScript(
                "(() => { const card = document.querySelector('[data-test=\"payout-wallet-card\"]'); const label = document.querySelector('[data-test=\"payout-status-label\"]'); if (!card || !label) { return false; } const cardBackground = getComputedStyle(card).backgroundColor; const labelColor = getComputedStyle(label).color; return cardBackground !== labelColor && labelColor !== 'rgba(0, 0, 0, 0)'; })()",
                true
            )
            ->assertNoJavaScriptErrors();
    });

    it('shows a warning when wallet data is unavailable', function () {
        $user = billingReadyUser();
        Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        UserPayoutAccount::factory()->enabled()->create([
            'user_id' => $user->id,
            'stripe_account_id' => 'acct_dashboard_wallet_unavailable',
        ]);

        $this->actingAs($user);

        $this->mock(PayoutWalletService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveBalance')
                ->atLeast()
                ->once()
                ->with('acct_dashboard_wallet_unavailable')
                ->andThrow(new RuntimeException('Stripe is down for testing'));
        });

        $page = visit('/dashboard');

        $page->assertSee('Stripe wallet data is temporarily unavailable. Retry in a moment.')
            ->assertNoJavaScriptErrors();
    });
});
