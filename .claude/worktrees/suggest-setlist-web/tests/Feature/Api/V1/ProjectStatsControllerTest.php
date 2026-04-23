<?php

declare(strict_types=1);

use App\Enums\RequestStatus;
use App\Models\AudienceProfile;
use App\Models\AudienceRewardClaim;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Request as SongRequest;
use App\Models\RewardThreshold;
use App\Models\Song;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\PaymentService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Stripe\PaymentIntent;

beforeEach(function () {
    Carbon::setTestNow('2026-03-12 18:00:00+00:00');

    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
        'created_at' => Carbon::parse('2025-11-03 18:30:00+00:00'),
        'updated_at' => Carbon::parse('2025-11-03 18:30:00+00:00'),
    ]);
    $this->payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->owner->id,
        'stripe_account_id' => 'acct_project_stats_test',
        'status' => UserPayoutAccount::STATUS_ENABLED,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);

    Sanctum::actingAs($this->owner);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns empty stats when the owner has no qualifying requests for today', function () {
    $response = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'today',
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('period.preset', 'today')
        ->assertJsonPath('period.local_start_date', '2026-03-12')
        ->assertJsonPath('period.local_end_date', '2026-03-12')
        ->assertJsonPath('money.gross_tip_amount_cents', 0)
        ->assertJsonPath('money.fee_amount_cents', 0)
        ->assertJsonPath('money.net_tip_amount_cents', 0)
        ->assertJsonPath('counts.request_count', 0)
        ->assertJsonPath('counts.played_count', 0)
        ->assertJsonPath('highlights.most_requested', null)
        ->assertJsonPath('highlights.highest_earning', null)
        ->assertJsonPath('rankings.most_played', [])
        ->assertJsonPath('rankings.most_requested', [])
        ->assertJsonPath('rankings.highest_earning', [])
        ->assertJsonPath('records.best_day', null)
        ->assertJsonPath('rewards_gifted.total', 0);

    // Default auto-created free-request threshold should appear with count 0.
    $rewards = $response->json('rewards_gifted.rewards');
    expect($rewards)->toBeArray()->not->toBeEmpty();
    expect($rewards[0]['count'])->toBe(0);
});

it('returns rewards_gifted totals scoped to the reporting window with per-threshold counts', function () {
    // Project auto-creates the default free-request threshold; add two more.
    $defaultThreshold = $this->project->rewardThresholds()->first();

    $drinkThreshold = RewardThreshold::factory()->create([
        'project_id' => $this->project->id,
        'reward_type' => 'drink',
        'reward_label' => 'Free Drink',
        'reward_icon' => 'local_bar',
        'sort_order' => 1,
    ]);
    $shoutoutThreshold = RewardThreshold::factory()->create([
        'project_id' => $this->project->id,
        'reward_type' => 'shoutout',
        'reward_label' => 'Shoutout',
        'reward_icon' => 'mic',
        'sort_order' => 2,
    ]);

    $fanProfile = AudienceProfile::factory()->create([
        'project_id' => $this->project->id,
    ]);
    $otherFanProfile = AudienceProfile::factory()->create([
        'project_id' => $this->project->id,
    ]);

    // Two free-request claims in the window.
    AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $fanProfile->id,
        'reward_threshold_id' => $defaultThreshold->id,
        'claimed_at' => Carbon::parse('2026-03-12 09:00:00+00:00'),
    ]);
    AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $otherFanProfile->id,
        'reward_threshold_id' => $defaultThreshold->id,
        'claimed_at' => Carbon::parse('2026-03-12 15:00:00+00:00'),
    ]);

    // One drink claim in the window.
    AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $fanProfile->id,
        'reward_threshold_id' => $drinkThreshold->id,
        'claimed_at' => Carbon::parse('2026-03-12 17:00:00+00:00'),
    ]);

    // Outside the window — should not count toward today's total.
    AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $fanProfile->id,
        'reward_threshold_id' => $shoutoutThreshold->id,
        'claimed_at' => Carbon::parse('2026-03-11 20:00:00+00:00'),
    ]);

    $response = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'UTC',
        'preset' => 'today',
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('rewards_gifted.total', 3);

    $rewards = $response->json('rewards_gifted.rewards');
    expect($rewards)->toHaveCount(3);

    // Ordered by sort_order ASC, id ASC: default (sort_order=0), drink (1), shoutout (2).
    expect($rewards[0])->toMatchArray([
        'reward_threshold_id' => $defaultThreshold->id,
        'reward_label' => RewardThreshold::DEFAULT_FREE_REQUEST_LABEL,
        'reward_icon' => RewardThreshold::DEFAULT_FREE_REQUEST_ICON,
        'count' => 2,
    ]);
    expect($rewards[1])->toMatchArray([
        'reward_threshold_id' => $drinkThreshold->id,
        'reward_label' => 'Free Drink',
        'reward_icon' => 'local_bar',
        'count' => 1,
    ]);
    // Shoutout has zero claims in the window but is still present with count 0.
    expect($rewards[2])->toMatchArray([
        'reward_threshold_id' => $shoutoutThreshold->id,
        'reward_label' => 'Shoutout',
        'reward_icon' => 'mic',
        'count' => 0,
    ]);
});

it('returns up to 10 ranking entries for most_played, most_requested, and highest_earning', function () {
    // Create 12 distinct songs, each with play/request/earning activity to
    // ensure at least 10 entries per ranking list are returned.
    $songs = collect(range(1, 12))->map(function (int $i): Song {
        $song = Song::factory()->create([
            'title' => sprintf('Song %02d', $i),
            'artist' => sprintf('Artist %02d', $i),
        ]);
        ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'song_id' => $song->id,
        ]);

        return $song;
    });

    foreach ($songs as $index => $song) {
        // Each song gets ($index + 1) played requests so rankings are distinct.
        $plays = $index + 1;
        for ($i = 0; $i < $plays; $i++) {
            SongRequest::factory()->played()->create([
                'project_id' => $this->project->id,
                'song_id' => $song->id,
                'tip_amount_cents' => 100 * ($index + 1),
                'score_cents' => 100 * ($index + 1),
                'payment_provider' => 'stripe',
                'payment_intent_id' => sprintf('pi_rank_%d_%d', $index, $i),
                'stripe_fee_amount_cents' => 0,
                'stripe_net_amount_cents' => 100 * ($index + 1),
                'created_at' => Carbon::parse('2026-03-12 08:00:00+00:00'),
                'updated_at' => Carbon::parse('2026-03-12 12:00:00+00:00'),
                'played_at' => Carbon::parse('2026-03-12 12:00:00+00:00'),
                'status' => RequestStatus::Played,
            ]);
        }
    }

    $response = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'UTC',
        'preset' => 'today',
    ]));

    $response->assertSuccessful();

    expect($response->json('rankings.most_played'))->toHaveCount(10);
    expect($response->json('rankings.most_requested'))->toHaveCount(10);
    expect($response->json('rankings.highest_earning'))->toHaveCount(10);
});

it('returns project stats with highlights and rankings while excluding placeholders', function () {
    $mostRequestedSong = Song::factory()->create([
        'title' => 'Most Requested',
        'artist' => 'The Crowd',
    ]);
    $highestEarningSong = Song::factory()->create([
        'title' => 'Top Earner',
        'artist' => 'The Cashes',
    ]);
    $secondPlayedSong = Song::factory()->create([
        'title' => 'Encore',
        'artist' => 'Closer',
    ]);
    $customSong = Song::factory()->create([
        'title' => 'Custom Mix',
        'artist' => 'DJ Guest',
    ]);
    $tipJarSupportSong = Song::tipJarSupportSong();
    $originalRequestSong = Song::originalRequestSong();

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $mostRequestedSong->id,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $highestEarningSong->id,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $secondPlayedSong->id,
    ]);

    SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $mostRequestedSong->id,
        'tip_amount_cents' => 1000,
        'score_cents' => 1000,
        'payment_provider' => 'stripe',
        'payment_intent_id' => 'pi_stats_ranked_one',
        'stripe_fee_amount_cents' => 59,
        'stripe_net_amount_cents' => 941,
        'created_at' => Carbon::parse('2026-03-12 08:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 08:00:00+00:00'),
        'status' => RequestStatus::Active,
    ]);
    SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $mostRequestedSong->id,
        'tip_amount_cents' => 500,
        'score_cents' => 500,
        'payment_provider' => 'none',
        'payment_intent_id' => null,
        'created_at' => Carbon::parse('2026-03-12 09:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 09:00:00+00:00'),
        'status' => RequestStatus::Active,
    ]);
    SongRequest::factory()->played()->create([
        'project_id' => $this->project->id,
        'song_id' => $highestEarningSong->id,
        'tip_amount_cents' => 2000,
        'score_cents' => 2000,
        'payment_provider' => 'stripe',
        'payment_intent_id' => 'pi_stats_ranked_two',
        'stripe_fee_amount_cents' => 88,
        'stripe_net_amount_cents' => 1912,
        'created_at' => Carbon::parse('2026-03-12 10:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 16:00:00+00:00'),
        'played_at' => Carbon::parse('2026-03-12 16:00:00+00:00'),
        'status' => RequestStatus::Played,
    ]);
    SongRequest::factory()->played()->create([
        'project_id' => $this->project->id,
        'song_id' => $secondPlayedSong->id,
        'tip_amount_cents' => 900,
        'score_cents' => 900,
        'payment_provider' => 'none',
        'payment_intent_id' => null,
        'created_at' => Carbon::parse('2026-03-12 10:30:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 12:30:00+00:00'),
        'played_at' => Carbon::parse('2026-03-12 12:30:00+00:00'),
        'status' => RequestStatus::Played,
    ]);
    SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $customSong->id,
        'tip_amount_cents' => 700,
        'score_cents' => 700,
        'payment_provider' => 'none',
        'payment_intent_id' => null,
        'created_at' => Carbon::parse('2026-03-12 11:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 11:00:00+00:00'),
        'status' => RequestStatus::Active,
    ]);
    SongRequest::factory()->played()->create([
        'project_id' => $this->project->id,
        'song_id' => $tipJarSupportSong->id,
        'tip_amount_cents' => 1500,
        'score_cents' => 1500,
        'payment_provider' => 'stripe',
        'payment_intent_id' => 'pi_stats_tip_jar',
        'stripe_fee_amount_cents' => 73,
        'stripe_net_amount_cents' => 1427,
        'created_at' => Carbon::parse('2026-03-12 12:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 12:00:00+00:00'),
        'played_at' => Carbon::parse('2026-03-12 12:00:00+00:00'),
        'status' => RequestStatus::Played,
    ]);
    SongRequest::factory()->played()->create([
        'project_id' => $this->project->id,
        'song_id' => $originalRequestSong->id,
        'tip_amount_cents' => 0,
        'score_cents' => 0,
        'payment_provider' => 'none',
        'payment_intent_id' => null,
        'created_at' => Carbon::parse('2026-03-12 13:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 15:00:00+00:00'),
        'played_at' => Carbon::parse('2026-03-12 15:00:00+00:00'),
        'status' => RequestStatus::Played,
    ]);

    $response = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'today',
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('period.preset', 'today')
        ->assertJsonPath('counts.request_count', 7)
        ->assertJsonPath('counts.played_count', 4)
        ->assertJsonPath('money.gross_tip_amount_cents', 4500)
        ->assertJsonPath('money.fee_amount_cents', 220)
        ->assertJsonPath('money.net_tip_amount_cents', 4280)
        ->assertJsonPath('money.cash_tip_amount_cents', 2100)
        ->assertJsonPath('highlights.most_requested.song_id', $mostRequestedSong->id)
        ->assertJsonPath('highlights.most_requested.request_count', 2)
        ->assertJsonPath('highlights.most_requested.net_tip_amount_cents', 941)
        ->assertJsonPath('highlights.highest_earning.song_id', $highestEarningSong->id)
        ->assertJsonPath('highlights.highest_earning.request_count', 1)
        ->assertJsonPath('highlights.highest_earning.net_tip_amount_cents', 1912)
        ->assertJsonPath('rankings.most_played.0.song_id', $secondPlayedSong->id)
        ->assertJsonPath('rankings.most_played.0.played_count', 1)
        ->assertJsonPath('rankings.most_played.1.song_id', $highestEarningSong->id)
        ->assertJsonPath('rankings.most_requested.0.song_id', $mostRequestedSong->id)
        ->assertJsonPath('rankings.highest_earning.0.song_id', $highestEarningSong->id);

    // tip_amount_distribution should only contain stripe tips (1000, 1500, 2000)
    $distribution = $response->json('tip_amount_distribution');
    $distributionAmounts = collect($distribution)->pluck('amount_cents')->sort()->values()->all();
    expect($distributionAmounts)->toBe([1000, 1500, 2000]);

    // fee_breakdown should only contain the stripe provider
    $feeBreakdown = $response->json('fee_breakdown');
    expect($feeBreakdown)->toHaveCount(1);
    expect($feeBreakdown[0]['provider'])->toBe('stripe');
    expect($feeBreakdown[0]['gross_cents'])->toBe(4500);
    expect($feeBreakdown[0]['fee_cents'])->toBe(220);
});

it('covers every supported preset window', function (
    string $preset,
    array $params,
    string $expectedStart,
    string $expectedEnd,
) {
    $response = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => $preset,
        ...$params,
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('period.preset', $preset)
        ->assertJsonPath('period.local_start_date', $expectedStart)
        ->assertJsonPath('period.local_end_date', $expectedEnd);
})->with([
    'today' => ['today', [], '2026-03-12', '2026-03-12'],
    'yesterday' => ['yesterday', [], '2026-03-11', '2026-03-11'],
    'this_week' => ['this_week', [], '2026-03-09', '2026-03-15'],
    'last_week' => ['last_week', [], '2026-03-02', '2026-03-08'],
    'this_month' => ['this_month', [], '2026-03-01', '2026-03-31'],
    'last_month' => ['last_month', [], '2026-02-01', '2026-02-28'],
    'this_year' => ['this_year', [], '2026-01-01', '2026-12-31'],
    'last_year' => ['last_year', [], '2025-01-01', '2025-12-31'],
    'all_time' => ['all_time', [], '2025-11-03', '2026-03-12'],
    'custom' => [
        'custom',
        ['start_date' => '2026-02-10', 'end_date' => '2026-02-12'],
        '2026-02-10',
        '2026-02-12',
    ],
]);

it('uses Monday-start week windows and played boundaries', function () {
    $boundarySong = Song::factory()->create([
        'title' => 'Boundary Song',
        'artist' => 'Clock Hands',
    ]);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $boundarySong->id,
    ]);

    SongRequest::factory()->played()->create([
        'project_id' => $this->project->id,
        'song_id' => $boundarySong->id,
        'tip_amount_cents' => 400,
        'score_cents' => 400,
        'payment_provider' => 'none',
        'payment_intent_id' => null,
        'created_at' => Carbon::parse('2026-03-02 07:30:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-02 07:30:00+00:00'),
        'played_at' => Carbon::parse('2026-03-09 05:59:59+00:00'),
        'status' => RequestStatus::Played,
    ]);
    SongRequest::factory()->played()->create([
        'project_id' => $this->project->id,
        'song_id' => $boundarySong->id,
        'tip_amount_cents' => 800,
        'score_cents' => 800,
        'payment_provider' => 'none',
        'payment_intent_id' => null,
        'created_at' => Carbon::parse('2026-03-09 06:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-09 06:00:00+00:00'),
        'played_at' => Carbon::parse('2026-03-09 06:00:00+00:00'),
        'status' => RequestStatus::Played,
    ]);

    $response = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'this_week',
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('period.local_start_date', '2026-03-09')
        ->assertJsonPath('period.local_end_date', '2026-03-15')
        ->assertJsonPath('counts.request_count', 1)
        ->assertJsonPath('counts.played_count', 1)
        ->assertJsonPath('rankings.most_played.0.song_id', $boundarySong->id)
        ->assertJsonPath('rankings.most_played.0.played_count', 1);
});

it('returns the project best-day gross-tip record scoped by local day', function () {
    $song = Song::factory()->create([
        'title' => 'Record Setter',
        'artist' => 'The Tippers',
    ]);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);

    $otherProject = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);

    SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 1500,
        'score_cents' => 1500,
        'payment_provider' => 'none',
        'created_at' => Carbon::parse('2026-03-11 18:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-11 18:00:00+00:00'),
    ]);
    SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 2200,
        'score_cents' => 2200,
        'payment_provider' => 'none',
        'created_at' => Carbon::parse('2026-03-12 02:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 02:00:00+00:00'),
    ]);
    SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 1200,
        'score_cents' => 1200,
        'payment_provider' => 'none',
        'created_at' => Carbon::parse('2026-03-12 16:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 16:00:00+00:00'),
    ]);
    SongRequest::factory()->create([
        'project_id' => $otherProject->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 9900,
        'score_cents' => 9900,
        'payment_provider' => 'none',
        'created_at' => Carbon::parse('2026-03-12 18:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 18:00:00+00:00'),
    ]);

    $response = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'today',
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('records.best_day.gross_tip_amount_cents', 3700)
        ->assertJsonPath('records.best_day.local_date', '2026-03-11')
        ->assertJsonPath('records.best_day.timezone', 'America/Denver')
        ->assertJsonPath('records.best_day.is_current_period_record', false);
});

it('validates custom query rules and non-custom date rejection', function () {
    $nonCustomResponse = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'today',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-02',
    ]));

    $nonCustomResponse->assertStatus(422)
        ->assertJsonValidationErrors(['start_date', 'end_date']);

    $missingCustomDates = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'custom',
    ]));

    $missingCustomDates->assertStatus(422)
        ->assertJsonValidationErrors(['start_date', 'end_date']);

    $invalidRange = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'custom',
        'start_date' => '2026-03-05',
        'end_date' => '2026-03-04',
    ]));

    $invalidRange->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);

    $futureRange = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'custom',
        'start_date' => '2026-03-12',
        'end_date' => '2026-03-13',
    ]));

    $futureRange->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);
});

it('returns forbidden for non-owners', function () {
    $member = User::factory()->create();
    Sanctum::actingAs($member);

    $response = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'today',
    ]));

    $response->assertForbidden();
});

it('hydrates missing stripe settlement data on demand', function () {
    $song = Song::factory()->create([
        'title' => 'Settlement Song',
        'artist' => 'Stripe Band',
    ]);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);

    $request = SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 3000,
        'score_cents' => 3000,
        'payment_provider' => 'stripe',
        'payment_intent_id' => 'pi_stats_missing_settlement',
        'stripe_fee_amount_cents' => null,
        'stripe_net_amount_cents' => null,
        'created_at' => Carbon::parse('2026-03-12 09:30:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 09:30:00+00:00'),
        'status' => RequestStatus::Active,
    ]);

    $paymentIntent = Mockery::mock(PaymentIntent::class);
    $paymentIntent->shouldReceive('toArray')
        ->once()
        ->andReturn([
            'id' => 'pi_stats_missing_settlement',
            'latest_charge' => [
                'balance_transaction' => [
                    'fee' => 117,
                    'net' => 2883,
                ],
            ],
        ]);

    $this->mock(PaymentService::class, function (MockInterface $mock) use ($paymentIntent) {
        $mock->shouldReceive('retrievePaymentIntentWithExpand')
            ->once()
            ->withArgs(
                fn (string $paymentIntentId, ?string $stripeAccountId, array $expand): bool => $paymentIntentId === 'pi_stats_missing_settlement'
                    && $stripeAccountId === 'acct_project_stats_test'
                    && $expand === ['latest_charge.balance_transaction']
            )
            ->andReturn($paymentIntent);
    });

    $response = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'today',
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('money.gross_tip_amount_cents', 3000)
        ->assertJsonPath('money.fee_amount_cents', 117)
        ->assertJsonPath('money.net_tip_amount_cents', 2883);

    $request->refresh();

    expect($request->stripe_fee_amount_cents)->toBe(117);
    expect($request->stripe_net_amount_cents)->toBe(2883);
});

it('falls back to gross amount when stripe settlement data cannot be resolved', function () {
    $song = Song::factory()->create([
        'title' => 'Unavailable Settlement',
        'artist' => 'Stripe Band',
    ]);

    SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 2500,
        'score_cents' => 2500,
        'payment_provider' => 'stripe',
        'payment_intent_id' => 'pi_stats_unavailable_settlement',
        'stripe_fee_amount_cents' => null,
        'stripe_net_amount_cents' => null,
        'created_at' => Carbon::parse('2026-03-12 10:30:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-12 10:30:00+00:00'),
        'status' => RequestStatus::Active,
    ]);

    $this->mock(PaymentService::class, function (MockInterface $mock) {
        $mock->shouldReceive('retrievePaymentIntentWithExpand')
            ->once()
            ->andThrow(new RuntimeException('Stripe unavailable'));
    });

    $response = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'today',
    ]));

    $response->assertOk()
        ->assertJsonPath('money.gross_tip_amount_cents', 2500)
        ->assertJsonPath('money.net_tip_amount_cents', 2500)
        ->assertJsonPath('money.fee_amount_cents', 0);
});

it('includes restored requests that predate the project created_at in all_time', function () {
    // Simulate a project whose created_at is recent (e.g. re-created),
    // but restored requests have earlier created_at timestamps.
    $this->project->forceFill(['created_at' => Carbon::parse('2026-03-12 12:00:00+00:00')])->saveQuietly();

    $song = Song::factory()->create([
        'title' => 'Restored Song',
        'artist' => 'Restored Artist',
    ]);

    SongRequest::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 1000,
        'score_cents' => 1000,
        'payment_provider' => 'stripe',
        'payment_intent_id' => 'pi_restored_old',
        'stripe_fee_amount_cents' => 59,
        'stripe_net_amount_cents' => 941,
        // This request predates the project's created_at:
        'created_at' => Carbon::parse('2026-03-01 10:00:00+00:00'),
        'updated_at' => Carbon::parse('2026-03-01 10:00:00+00:00'),
        'status' => RequestStatus::Played,
        'played_at' => Carbon::parse('2026-03-01 10:00:00+00:00'),
    ]);

    $response = $this->getJson(projectStatsUrl($this->project, [
        'timezone' => 'America/Denver',
        'preset' => 'all_time',
    ]));

    $response->assertOk()
        ->assertJsonPath('money.gross_tip_amount_cents', 1000)
        ->assertJsonPath('money.net_tip_amount_cents', 941)
        ->assertJsonPath('money.fee_amount_cents', 59)
        ->assertJsonPath('counts.request_count', 1);
});

function projectStatsUrl(Project $project, array $params): string
{
    return '/api/v1/me/projects/'.$project->id.'/stats?'.http_build_query($params);
}
