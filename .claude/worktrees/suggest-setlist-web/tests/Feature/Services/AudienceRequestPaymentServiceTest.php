<?php

declare(strict_types=1);

use App\Mail\RewardEarnedMail;
use App\Models\AudienceProfile;
use App\Models\AudienceRewardClaim;
use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\RewardThreshold;
use App\Models\Song;
use App\Services\AudienceRequestPaymentService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;
use Stripe\PaymentIntent;

it('returns null for empty payment intent id from payload', function () {
    $service = app(AudienceRequestPaymentService::class);

    $result = $service->findOrCreateFromPaymentIntentPayload([
        'id' => '',
    ]);

    expect($result)->toBeNull();
});

it('returns null when project or song not found', function () {
    $service = app(AudienceRequestPaymentService::class);

    $result = $service->findOrCreateFromPaymentIntentPayload([
        'id' => 'pi_test_nonexistent',
        'metadata' => [
            'project_id' => 99999,
            'song_id' => 99999,
        ],
        'amount_received' => 500,
    ]);

    expect($result)->toBeNull();
});

it('returns null when song_id is 0 and not tip only', function () {
    $service = app(AudienceRequestPaymentService::class);

    $result = $service->findOrCreateFromPaymentIntentPayload([
        'id' => 'pi_test_nosong',
        'metadata' => [
            'project_id' => 1,
            'song_id' => 0,
            'tip_only' => '0',
        ],
        'amount_received' => 500,
    ]);

    expect($result)->toBeNull();
});

it('creates a request from valid payment intent payload with visitor token', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();

    $service = app(AudienceRequestPaymentService::class);

    $result = $service->findOrCreateFromPaymentIntentPayload([
        'id' => 'pi_test_with_visitor_'.uniqid(),
        'metadata' => [
            'project_id' => (string) $project->id,
            'song_id' => (string) $song->id,
            'visitor_token' => 'test-visitor-token-123',
            'requested_from_ip' => '1.2.3.4',
            'note' => 'Please play this!',
        ],
        'amount_received' => 500,
    ]);

    expect($result)->not->toBeNull()
        ->and($result->tip_amount_cents)->toBe(500)
        ->and($result->audience_profile_id)->not->toBeNull()
        ->and($result->note)->toBe('Please play this!');
});

it('returns existing request for duplicate payment intent', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();
    $piId = 'pi_duplicate_test_'.uniqid();

    $existing = SongRequest::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'payment_intent_id' => $piId,
        'payment_provider' => 'stripe',
    ]);

    $service = app(AudienceRequestPaymentService::class);

    $result = $service->findOrCreateFromPaymentIntentPayload([
        'id' => $piId,
        'metadata' => [
            'project_id' => (string) $project->id,
            'song_id' => (string) $song->id,
        ],
        'amount_received' => 500,
    ]);

    expect($result->id)->toBe($existing->id);
});

it('syncs settlement from payment intent payload', function () {
    $piId = 'pi_settlement_'.uniqid();
    $request = SongRequest::factory()->create([
        'payment_intent_id' => $piId,
        'payment_provider' => 'stripe',
        'stripe_fee_amount_cents' => null,
        'stripe_net_amount_cents' => null,
    ]);

    $service = app(AudienceRequestPaymentService::class);

    $result = $service->syncSettlementFromPaymentIntentPayload([
        'id' => $piId,
        'latest_charge' => [
            'balance_transaction' => [
                'fee' => 45,
                'net' => 455,
            ],
        ],
    ]);

    expect($result->stripe_fee_amount_cents)->toBe(45)
        ->and($result->stripe_net_amount_cents)->toBe(455);
});

it('syncs settlement from charge payload', function () {
    $piId = 'pi_charge_settlement_'.uniqid();
    $request = SongRequest::factory()->create([
        'payment_intent_id' => $piId,
        'payment_provider' => 'stripe',
        'stripe_fee_amount_cents' => null,
        'stripe_net_amount_cents' => null,
    ]);

    $service = app(AudienceRequestPaymentService::class);

    $result = $service->syncSettlementFromChargePayload([
        'payment_intent' => $piId,
        'balance_transaction' => [
            'fee' => 30,
            'net' => 470,
        ],
    ]);

    expect($result->stripe_fee_amount_cents)->toBe(30)
        ->and($result->stripe_net_amount_cents)->toBe(470);
});

it('returns null for empty charge payload payment intent', function () {
    $service = app(AudienceRequestPaymentService::class);

    $result = $service->syncSettlementFromChargePayload(['payment_intent' => '']);

    expect($result)->toBeNull();
});

it('returns null for empty settlement payload payment intent', function () {
    $service = app(AudienceRequestPaymentService::class);

    $result = $service->syncSettlementFromPaymentIntentPayload(['id' => '']);

    expect($result)->toBeNull();
});

it('returns null settlement when request not found', function () {
    $service = app(AudienceRequestPaymentService::class);

    $result = $service->syncSettlementFromPaymentIntentPayload([
        'id' => 'pi_nonexistent_'.uniqid(),
    ]);

    expect($result)->toBeNull();
});

it('returns request unchanged when settlement amounts are non-numeric', function () {
    $piId = 'pi_nonnumeric_'.uniqid();
    $request = SongRequest::factory()->create([
        'payment_intent_id' => $piId,
        'payment_provider' => 'stripe',
    ]);

    $service = app(AudienceRequestPaymentService::class);

    $result = $service->syncSettlementFromPaymentIntentPayload([
        'id' => $piId,
        'latest_charge' => [
            'balance_transaction' => [
                'fee' => null,
                'net' => null,
            ],
        ],
    ]);

    expect($result->id)->toBe($request->id);
});

it('calculates queue position correctly', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();

    $highTip = SongRequest::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 1000,
        'score_cents' => 1000,
        'status' => 'active',
        'created_at' => now()->subMinute(),
    ]);

    $lowTip = SongRequest::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 500,
        'score_cents' => 500,
        'status' => 'active',
        'created_at' => now(),
    ]);

    $service = app(AudienceRequestPaymentService::class);

    expect($service->queuePosition($highTip))->toBe(1)
        ->and($service->queuePosition($lowTip))->toBe(2);
});

it('generates queue position message', function () {
    $service = app(AudienceRequestPaymentService::class);

    expect($service->queuePositionMessage(3))
        ->toBe("Request submitted. You're currently #3 in the queue.");
});

it('returns null for empty findOrCreateFromPaymentIntentId', function () {
    $service = app(AudienceRequestPaymentService::class);

    expect($service->findOrCreateFromPaymentIntentId('', ''))->toBeNull()
        ->and($service->findOrCreateFromPaymentIntentId('pi_123', ''))->toBeNull();
});

it('returns true for syncSettlementForRequest when not stripe payment', function () {
    $request = SongRequest::factory()->create([
        'payment_provider' => 'free',
        'payment_intent_id' => null,
    ]);

    $service = app(AudienceRequestPaymentService::class);

    expect($service->syncSettlementForRequest($request, 'acct_123'))->toBeTrue();
});

it('returns true for syncSettlementForRequest when amounts already set', function () {
    $request = SongRequest::factory()->create([
        'payment_provider' => 'stripe',
        'payment_intent_id' => 'pi_already_synced',
        'stripe_fee_amount_cents' => 45,
        'stripe_net_amount_cents' => 455,
    ]);

    $service = app(AudienceRequestPaymentService::class);

    expect($service->syncSettlementForRequest($request, 'acct_123'))->toBeTrue();
});

it('updates audience profile display_name from billing_details on payload creation', function () {
    Mail::fake();

    $project = Project::factory()->create();
    $song = Song::factory()->create();

    $service = app(AudienceRequestPaymentService::class);

    $result = $service->findOrCreateFromPaymentIntentPayload([
        'id' => 'pi_billing_name_'.uniqid(),
        'metadata' => [
            'project_id' => (string) $project->id,
            'song_id' => (string) $song->id,
            'visitor_token' => 'billing-test-token-'.uniqid(),
            'tip_only' => '1',
        ],
        'amount_received' => 1000,
        'latest_charge' => [
            'billing_details' => [
                'name' => 'Sarah Connor',
                'email' => 'sarah@example.com',
            ],
        ],
    ]);

    expect($result)->not->toBeNull()
        ->and($result->audience_profile_id)->not->toBeNull();

    $profile = AudienceProfile::find($result->audience_profile_id);

    expect($profile->display_name)->toBe('Sarah Connor')
        ->and($profile->email)->toBe('sarah@example.com');
});

it('backfills audience profile from stripe when request already exists with null display_name', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();
    $piId = 'pi_backfill_'.uniqid();

    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'display_name' => null,
        'email' => null,
    ]);

    $existing = SongRequest::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'audience_profile_id' => $profile->id,
        'payment_intent_id' => $piId,
        'payment_provider' => 'stripe',
    ]);

    $mockPaymentService = Mockery::mock(PaymentService::class);
    $mockPaymentService->shouldReceive('retrievePaymentIntentWithExpand')
        ->once()
        ->with($piId, 'acct_test_backfill', ['latest_charge.billing_details'])
        ->andReturn(PaymentIntent::constructFrom([
            'id' => $piId,
            'object' => 'payment_intent',
            'status' => 'succeeded',
            'latest_charge' => [
                'billing_details' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ],
        ]));
    app()->instance(PaymentService::class, $mockPaymentService);

    $service = app(AudienceRequestPaymentService::class);
    $result = $service->findOrCreateFromPaymentIntentId($piId, 'acct_test_backfill');

    expect($result->id)->toBe($existing->id);

    $profile->refresh();
    expect($profile->display_name)->toBe('John Doe')
        ->and($profile->email)->toBe('john@example.com');
});

// --- Pending physical reward auto-creation on tip earn ---

it('creates a pending physical reward claim when a new threshold is crossed', function () {
    Mail::fake();

    $project = Project::factory()->create(['notify_on_request' => true]);
    $project->rewardThresholds()->delete();
    $threshold = RewardThreshold::factory()
        ->withType('free_cd', 'Free CD')
        ->create([
            'project_id' => $project->id,
            'threshold_cents' => 1000,
            'is_repeating' => false,
        ]);
    $song = Song::factory()->create();

    $service = app(AudienceRequestPaymentService::class);

    $service->findOrCreateFromPaymentIntentPayload([
        'id' => 'pi_physical_reward_'.uniqid(),
        'metadata' => [
            'project_id' => (string) $project->id,
            'song_id' => (string) $song->id,
            'visitor_token' => 'reward-test-token-'.uniqid(),
        ],
        'amount_received' => 1500,
    ]);

    $claims = AudienceRewardClaim::query()
        ->where('reward_threshold_id', $threshold->id)
        ->get();

    expect($claims)->toHaveCount(1)
        ->and($claims->first()->claimed_at)->toBeNull();

    Mail::assertQueued(RewardEarnedMail::class);
});

it('creates multiple pending claims when crossing multiple repeating tiers in one tip', function () {
    Mail::fake();

    $project = Project::factory()->create(['notify_on_request' => false]);
    $project->rewardThresholds()->delete();
    $threshold = RewardThreshold::factory()
        ->withType('free_cd', 'Free CD')
        ->create([
            'project_id' => $project->id,
            'threshold_cents' => 1000,
            'is_repeating' => true,
        ]);
    $song = Song::factory()->create();

    $service = app(AudienceRequestPaymentService::class);

    // Single tip of $30 crosses 3 tiers ($10, $20, $30).
    $service->findOrCreateFromPaymentIntentPayload([
        'id' => 'pi_multi_tier_'.uniqid(),
        'metadata' => [
            'project_id' => (string) $project->id,
            'song_id' => (string) $song->id,
            'visitor_token' => 'multi-tier-token-'.uniqid(),
        ],
        'amount_received' => 3000,
    ]);

    $pendingCount = AudienceRewardClaim::query()
        ->where('reward_threshold_id', $threshold->id)
        ->pending()
        ->count();

    expect($pendingCount)->toBe(3);
});

it('does not create pending claims for free_request thresholds', function () {
    Mail::fake();

    $project = Project::factory()->create(['notify_on_request' => false]);
    // Default factory creates a free_request threshold at 4000 cents.
    $threshold = $project->rewardThresholds()->first();
    $song = Song::factory()->create();

    $service = app(AudienceRequestPaymentService::class);

    $service->findOrCreateFromPaymentIntentPayload([
        'id' => 'pi_free_request_'.uniqid(),
        'metadata' => [
            'project_id' => (string) $project->id,
            'song_id' => (string) $song->id,
            'visitor_token' => 'free-request-token-'.uniqid(),
        ],
        'amount_received' => 5000,
    ]);

    $count = AudienceRewardClaim::query()
        ->where('reward_threshold_id', $threshold->id)
        ->count();

    expect($count)->toBe(0);
});

it('still creates pending physical claims when notify_on_request is disabled', function () {
    Mail::fake();

    $project = Project::factory()->create(['notify_on_request' => false]);
    $project->rewardThresholds()->delete();
    $threshold = RewardThreshold::factory()
        ->withType('free_cd', 'Free CD')
        ->create([
            'project_id' => $project->id,
            'threshold_cents' => 1000,
            'is_repeating' => false,
        ]);
    $song = Song::factory()->create();

    $service = app(AudienceRequestPaymentService::class);

    $service->findOrCreateFromPaymentIntentPayload([
        'id' => 'pi_no_notify_'.uniqid(),
        'metadata' => [
            'project_id' => (string) $project->id,
            'song_id' => (string) $song->id,
            'visitor_token' => 'no-notify-token-'.uniqid(),
        ],
        'amount_received' => 1500,
    ]);

    $count = AudienceRewardClaim::query()
        ->where('reward_threshold_id', $threshold->id)
        ->pending()
        ->count();

    expect($count)->toBe(1);
    Mail::assertNotQueued(RewardEarnedMail::class);
});

it('does not overwrite existing audience profile display_name during backfill', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();
    $piId = 'pi_no_overwrite_'.uniqid();

    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'display_name' => 'Existing Name',
        'email' => 'existing@example.com',
    ]);

    SongRequest::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'audience_profile_id' => $profile->id,
        'payment_intent_id' => $piId,
        'payment_provider' => 'stripe',
    ]);

    $mockPaymentService = Mockery::mock(PaymentService::class);
    $mockPaymentService->shouldNotReceive('retrievePaymentIntentWithExpand');
    app()->instance(PaymentService::class, $mockPaymentService);

    $service = app(AudienceRequestPaymentService::class);
    $service->findOrCreateFromPaymentIntentId($piId, 'acct_test_no_overwrite');

    $profile->refresh();
    expect($profile->display_name)->toBe('Existing Name')
        ->and($profile->email)->toBe('existing@example.com');
});
