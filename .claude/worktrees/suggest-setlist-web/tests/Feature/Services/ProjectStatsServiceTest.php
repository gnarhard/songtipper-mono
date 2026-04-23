<?php

declare(strict_types=1);

use App\Enums\StatsTimelinePreset;
use App\Models\AudienceProfile;
use App\Models\AudienceRewardClaim;
use App\Models\CashTip;
use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\RewardThreshold;
use App\Services\ProjectStatsService;
use Illuminate\Support\Carbon;

it('returns null daily record event when no requests exist', function () {
    $project = Project::factory()->create();

    $service = app(ProjectStatsService::class);
    $result = $service->dailyRecordEvent($project, 'America/New_York');

    expect($result)->toBeNull();
});

it('returns null daily record event when today total is zero', function () {
    $project = Project::factory()->create();

    // Create request from yesterday, not today
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 500,
        'created_at' => now()->subDay(),
    ]);

    $service = app(ProjectStatsService::class);
    $result = $service->dailyRecordEvent($project, 'UTC');

    expect($result)->toBeNull();
});

it('returns null daily record event when today is not a new record', function () {
    $project = Project::factory()->create();

    // Create a past request with higher amount
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 5000,
        'created_at' => now()->subDays(3),
    ]);

    // Create today's request with lower amount
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 500,
        'created_at' => now(),
    ]);

    $service = app(ProjectStatsService::class);
    $result = $service->dailyRecordEvent($project, 'UTC');

    expect($result)->toBeNull();
});

it('returns daily record event when today beats previous best', function () {
    $project = Project::factory()->create();

    // Create a past request with lower amount
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 100,
        'created_at' => now()->subDays(3),
    ]);

    // Create today's request with higher amount
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 500,
        'created_at' => now(),
    ]);

    $service = app(ProjectStatsService::class);
    $result = $service->dailyRecordEvent($project, 'UTC');

    expect($result)->not->toBeNull()
        ->and($result['gross_tip_amount_cents'])->toBe(500);
});

it('generates report for today preset', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $service = app(ProjectStatsService::class);
    $result = $service->report(
        $project,
        StatsTimelinePreset::Today,
        'UTC',
    );

    expect($result['period']['preset'])->toBe('today')
        ->and($result['money'])->toHaveKeys(['gross_tip_amount_cents', 'fee_amount_cents', 'net_tip_amount_cents', 'cash_tip_amount_cents'])
        ->and($result['counts'])->toHaveKeys(['request_count', 'played_count']);
});

it('generates report for all_time preset', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $service = app(ProjectStatsService::class);
    $result = $service->report(
        $project,
        StatsTimelinePreset::AllTime,
        'UTC',
    );

    expect($result['period']['preset'])->toBe('all_time');
});

it('generates report for custom preset', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $service = app(ProjectStatsService::class);
    $result = $service->report(
        $project,
        StatsTimelinePreset::Custom,
        'UTC',
        '2025-01-01',
        '2025-01-31',
    );

    expect($result['period']['preset'])->toBe('custom')
        ->and($result['period']['local_start_date'])->toBe('2025-01-01')
        ->and($result['period']['local_end_date'])->toBe('2025-01-31');
});

it('caches daily record event', function () {
    $project = Project::factory()->create();

    $service = app(ProjectStatsService::class);
    $result1 = $service->cachedDailyRecordEvent($project, 'UTC');
    $result2 = $service->cachedDailyRecordEvent($project, 'UTC');

    expect($result1)->toBe($result2);
});

it('includes cash_tip_amount_cents in report money', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $todayDate = now('UTC')->toDateString();
    CashTip::factory()->create([
        'project_id' => $project->id,
        'amount_cents' => 3000,
        'local_date' => $todayDate,
    ]);
    CashTip::factory()->create([
        'project_id' => $project->id,
        'amount_cents' => 1500,
        'local_date' => $todayDate,
    ]);

    $service = app(ProjectStatsService::class);
    $result = $service->report($project, StatsTimelinePreset::Today, 'UTC');

    expect($result['money']['cash_tip_amount_cents'])->toBe(4500);
});

it('excludes cash tips outside the reporting window', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $todayDate = now('UTC')->toDateString();
    $yesterdayDate = now('UTC')->subDay()->toDateString();

    CashTip::factory()->create([
        'project_id' => $project->id,
        'amount_cents' => 2000,
        'local_date' => $todayDate,
    ]);
    CashTip::factory()->create([
        'project_id' => $project->id,
        'amount_cents' => 5000,
        'local_date' => $yesterdayDate,
    ]);

    $service = app(ProjectStatsService::class);
    $result = $service->report($project, StatsTimelinePreset::Today, 'UTC');

    expect($result['money']['cash_tip_amount_cents'])->toBe(2000);
});

it('includes rewards_gifted block with zero totals by default', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $service = app(ProjectStatsService::class);
    $result = $service->report($project, StatsTimelinePreset::Today, 'UTC');

    expect($result)->toHaveKey('rewards_gifted');
    expect($result['rewards_gifted']['total'])->toBe(0);
    // Default auto-created free-request threshold should appear with count 0.
    expect($result['rewards_gifted']['rewards'])->not->toBeEmpty();
    expect($result['rewards_gifted']['rewards'][0]['count'])->toBe(0);
});

it('counts rewards_gifted claims scoped to the reporting window', function () {
    Carbon::setTestNow('2026-03-12 18:00:00+00:00');

    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);
    $defaultThreshold = $project->rewardThresholds()->first();

    $fan = AudienceProfile::factory()->create(['project_id' => $project->id]);

    // Inside the window (today UTC).
    AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $fan->id,
        'reward_threshold_id' => $defaultThreshold->id,
        'claimed_at' => Carbon::parse('2026-03-12 09:00:00+00:00'),
    ]);
    // Outside the window (yesterday UTC).
    AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $fan->id,
        'reward_threshold_id' => $defaultThreshold->id,
        'claimed_at' => Carbon::parse('2026-03-11 09:00:00+00:00'),
    ]);

    $service = app(ProjectStatsService::class);
    $result = $service->report($project, StatsTimelinePreset::Today, 'UTC');

    expect($result['rewards_gifted']['total'])->toBe(1);
    expect($result['rewards_gifted']['rewards'][0]['count'])->toBe(1);

    Carbon::setTestNow();
});

it('lists rewards_gifted thresholds in sort order with zero counts for thresholds with no claims', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    // Default free-request threshold already exists at sort_order 0.
    RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'reward_label' => 'Signed Poster',
        'reward_icon' => 'card_giftcard',
        'sort_order' => 2,
    ]);
    RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'reward_label' => 'Drink Token',
        'reward_icon' => 'local_bar',
        'sort_order' => 1,
    ]);

    $service = app(ProjectStatsService::class);
    $result = $service->report($project, StatsTimelinePreset::Today, 'UTC');

    $rewards = $result['rewards_gifted']['rewards'];
    expect($rewards)->toHaveCount(3);
    expect(array_column($rewards, 'reward_label'))->toBe([
        RewardThreshold::DEFAULT_FREE_REQUEST_LABEL,
        'Drink Token',
        'Signed Poster',
    ]);
    expect(array_column($rewards, 'count'))->toBe([0, 0, 0]);
    expect($result['rewards_gifted']['total'])->toBe(0);
});

it('includes cash tips in daily record event calculation', function () {
    $project = Project::factory()->create();
    $todayDate = now('UTC')->toDateString();

    // Past request with 500 cents
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 500,
        'created_at' => now()->subDays(3),
    ]);

    // Today's request with only 100 cents (not enough on its own)
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'tip_amount_cents' => 100,
        'created_at' => now(),
    ]);

    // Today's cash tip pushes today over the previous best
    CashTip::factory()->create([
        'project_id' => $project->id,
        'amount_cents' => 500,
        'local_date' => $todayDate,
    ]);

    $service = app(ProjectStatsService::class);
    $result = $service->dailyRecordEvent($project, 'UTC');

    expect($result)->not->toBeNull()
        ->and($result['gross_tip_amount_cents'])->toBe(600);
});
