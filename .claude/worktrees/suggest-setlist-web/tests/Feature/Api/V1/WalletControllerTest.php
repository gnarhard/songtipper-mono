<?php

declare(strict_types=1);

use App\Enums\RequestStatus;
use App\Models\PerformanceSession;
use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\Setlist;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\PayoutAccountService;
use App\Services\PayoutWalletService;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $this->payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->owner->id,
        'stripe_account_id' => 'acct_wallet_test_1',
        'status' => UserPayoutAccount::STATUS_ENABLED,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);
    Sanctum::actingAs($this->owner);
});

it('returns project wallet summary for owners', function () {
    $this->mock(PayoutAccountService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getForUser')
            ->once()
            ->withArgs(fn (User $user, bool $refresh): bool => $user->id === $this->owner->id && $refresh)
            ->andReturn($this->payoutAccount);
    });

    $this->mock(PayoutWalletService::class, function (MockInterface $mock) {
        $mock->shouldReceive('retrieveBalance')
            ->once()
            ->with('acct_wallet_test_1')
            ->andReturn([
                'available' => [['currency' => 'usd', 'amount_cents' => 2300]],
                'pending' => [['currency' => 'usd', 'amount_cents' => 700]],
                'available_total_cents' => 2300,
                'pending_total_cents' => 700,
                'retrieved_at' => now()->toIso8601String(),
            ]);
        $mock->shouldReceive('projectEarningsSummary')
            ->once()
            ->withArgs(fn (Project $project): bool => $project->id === $this->project->id)
            ->andReturn([
                'total_tip_amount_cents' => 3000,
                'gross_tip_amount_cents' => 3000,
                'fee_amount_cents' => 180,
                'net_tip_amount_cents' => 2820,
                'paid_request_count' => 3,
                'active_queue_tip_amount_cents' => 1000,
                'active_queue_request_count' => 1,
                'active_session_tip_amount_cents' => 2000,
                'sessionless_tip_amount_cents' => 500,
            ]);
    });

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/wallet");

    $response->assertSuccessful()
        ->assertJsonPath('wallet.scope', 'account_level')
        ->assertJsonPath('wallet.stripe_balance.available_total_cents', 2300)
        ->assertJsonPath('wallet.project_earnings.total_tip_amount_cents', 3000)
        ->assertJsonPath('wallet.project_earnings.gross_tip_amount_cents', 3000)
        ->assertJsonPath('wallet.project_earnings.fee_amount_cents', 180)
        ->assertJsonPath('wallet.project_earnings.net_tip_amount_cents', 2820)
        ->assertJsonPath('payout_account.status', UserPayoutAccount::STATUS_ENABLED);
});

it('returns session earnings for owners', function () {
    $setlist = Setlist::factory()->create(['project_id' => $this->project->id]);
    $olderSession = PerformanceSession::factory()->create([
        'project_id' => $this->project->id,
        'setlist_id' => $setlist->id,
        'is_active' => false,
        'started_at' => now()->subHours(3),
        'ended_at' => now()->subHours(2),
    ]);
    $newerSession = PerformanceSession::factory()->create([
        'project_id' => $this->project->id,
        'setlist_id' => $setlist->id,
        'is_active' => true,
        'started_at' => now()->subHour(),
        'ended_at' => null,
    ]);

    SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'performance_session_id' => $olderSession->id,
        'payment_provider' => 'stripe',
        'tip_amount_cents' => 1200,
        'score_cents' => 1200,
        'status' => RequestStatus::Played,
    ]);
    SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'performance_session_id' => $olderSession->id,
        'payment_provider' => 'none',
        'tip_amount_cents' => 500,
        'score_cents' => 500,
        'status' => RequestStatus::Played,
    ]);
    SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'performance_session_id' => $newerSession->id,
        'payment_provider' => 'stripe',
        'tip_amount_cents' => 1800,
        'score_cents' => 1800,
        'status' => RequestStatus::Active,
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/wallet/sessions?per_page=10");

    $response->assertSuccessful()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $newerSession->id)
        ->assertJsonPath('data.0.total_tip_amount_cents', 1800)
        ->assertJsonPath('data.1.id', $olderSession->id)
        ->assertJsonPath('data.1.total_tip_amount_cents', 1200);
});

it('returns empty payouts when payout setup is not started', function () {
    $userWithoutPayout = User::factory()->create();
    Sanctum::actingAs($userWithoutPayout);

    $response = $this->getJson('/api/v1/me/payouts');

    $response->assertSuccessful()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('payout_account.status', UserPayoutAccount::STATUS_NOT_STARTED);
});

it('returns payouts from stripe for connected accounts', function () {
    $this->mock(PayoutAccountService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getForUser')
            ->once()
            ->withArgs(fn (User $user, bool $refresh): bool => $user->id === $this->owner->id && $refresh)
            ->andReturn($this->payoutAccount);
    });

    $this->mock(PayoutWalletService::class, function (MockInterface $mock) {
        $mock->shouldReceive('listPayouts')
            ->once()
            ->with('acct_wallet_test_1', 5, 'paid')
            ->andReturn([
                'data' => [[
                    'id' => 'po_test_1',
                    'amount_cents' => 2500,
                    'currency' => 'usd',
                    'status' => 'paid',
                    'method' => 'standard',
                    'type' => 'bank_account',
                    'description' => null,
                    'arrival_date' => now()->toIso8601String(),
                    'created_at' => now()->toIso8601String(),
                    'failure_code' => null,
                    'failure_message' => null,
                ]],
                'has_more' => false,
            ]);
    });

    $response = $this->getJson('/api/v1/me/payouts?limit=5&status=paid');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.id', 'po_test_1')
        ->assertJsonPath('meta.limit', 5)
        ->assertJsonPath('meta.status', 'paid')
        ->assertJsonPath('meta.has_more', false);
});

it('blocks wallet endpoints for non-owners', function () {
    $member = User::factory()->create();
    Sanctum::actingAs($member);

    $walletResponse = $this->getJson("/api/v1/me/projects/{$this->project->id}/wallet");
    $sessionsResponse = $this->getJson("/api/v1/me/projects/{$this->project->id}/wallet/sessions");

    $walletResponse->assertForbidden();
    $sessionsResponse->assertForbidden();
});

it('returns 502 when stripe balance retrieval fails', function () {
    $this->mock(PayoutAccountService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getForUser')
            ->once()
            ->andReturn($this->payoutAccount);
    });

    $this->mock(PayoutWalletService::class, function (MockInterface $mock) {
        $mock->shouldReceive('retrieveBalance')
            ->once()
            ->andThrow(new RuntimeException('Stripe API unreachable'));
    });

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/wallet");

    $response->assertStatus(502)
        ->assertJsonPath('code', 'stripe_wallet_unavailable')
        ->assertJsonPath('message', 'Unable to load Stripe wallet data right now.');
});

it('returns empty balance when payout account has no stripe id', function () {
    $this->mock(PayoutAccountService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getForUser')
            ->once()
            ->andReturn(null);
    });

    $this->mock(PayoutWalletService::class, function (MockInterface $mock) {
        $mock->shouldReceive('projectEarningsSummary')
            ->once()
            ->andReturn([
                'total_tip_amount_cents' => 0,
                'gross_tip_amount_cents' => 0,
                'fee_amount_cents' => 0,
                'net_tip_amount_cents' => 0,
                'paid_request_count' => 0,
                'active_queue_tip_amount_cents' => 0,
                'active_queue_request_count' => 0,
                'active_session_tip_amount_cents' => 0,
                'sessionless_tip_amount_cents' => 0,
            ]);
    });

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/wallet");

    $response->assertSuccessful()
        ->assertJsonPath('wallet.stripe_balance.available_total_cents', 0)
        ->assertJsonPath('wallet.stripe_balance.pending_total_cents', 0);
});

it('returns 502 when payouts stripe retrieval fails', function () {
    $this->mock(PayoutAccountService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getForUser')
            ->once()
            ->andReturn($this->payoutAccount);
    });

    $this->mock(PayoutWalletService::class, function (MockInterface $mock) {
        $mock->shouldReceive('listPayouts')
            ->once()
            ->andThrow(new RuntimeException('Stripe payouts API error'));
    });

    $response = $this->getJson('/api/v1/me/payouts');

    $response->assertStatus(502)
        ->assertJsonPath('code', 'stripe_wallet_unavailable')
        ->assertJsonPath('message', 'Unable to load payout history right now.');
});

it('returns null payout account data when none exists', function () {
    $userWithoutPayout = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);
    Sanctum::actingAs($userWithoutPayout);

    $this->mock(PayoutAccountService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getForUser')
            ->once()
            ->andReturn(null);
    });

    $response = $this->getJson('/api/v1/me/payouts');

    $response->assertSuccessful()
        ->assertJsonPath('payout_account.status', UserPayoutAccount::STATUS_NOT_STARTED)
        ->assertJsonPath('payout_account.setup_complete', false)
        ->assertJsonCount(0, 'data');
});
