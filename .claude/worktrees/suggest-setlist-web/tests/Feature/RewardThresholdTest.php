<?php

declare(strict_types=1);

use App\Models\AudienceProfile;
use App\Models\AudienceRewardClaim;
use App\Models\Project;
use App\Models\RewardThreshold;
use App\Models\User;
use App\Services\RewardThresholdService;
use Laravel\Sanctum\Sanctum;

// --- RewardThresholdService ---

it('calculates available claims for a repeating threshold', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'threshold_cents' => 4000,
        'is_repeating' => true,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 12000,
    ]);

    $service = app(RewardThresholdService::class);

    expect($service->availableClaims($profile, $threshold))->toBe(3);
});

it('reduces available claims by existing claims', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'threshold_cents' => 4000,
        'is_repeating' => true,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 12000,
    ]);

    AudienceRewardClaim::factory()->count(2)->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    $service = app(RewardThresholdService::class);

    expect($service->availableClaims($profile, $threshold))->toBe(1);
});

it('returns zero available claims when all are claimed', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'threshold_cents' => 4000,
        'is_repeating' => true,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 8000,
    ]);

    AudienceRewardClaim::factory()->count(2)->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    $service = app(RewardThresholdService::class);

    expect($service->availableClaims($profile, $threshold))->toBe(0);
});

it('returns one available claim for a non-repeating threshold when eligible and unclaimed', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->nonRepeating()->create([
        'project_id' => $project->id,
        'threshold_cents' => 8000,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 10000,
    ]);

    $service = app(RewardThresholdService::class);

    expect($service->availableClaims($profile, $threshold))->toBe(1);
});

it('returns zero for a non-repeating threshold after claiming', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->nonRepeating()->create([
        'project_id' => $project->id,
        'threshold_cents' => 8000,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 20000,
    ]);

    AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    $service = app(RewardThresholdService::class);

    expect($service->availableClaims($profile, $threshold))->toBe(0);
});

it('calculates cents until next claim for repeating threshold', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'threshold_cents' => 4000,
        'is_repeating' => true,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 3000,
    ]);

    $service = app(RewardThresholdService::class);

    expect($service->centsUntilNextClaim($profile, $threshold))->toBe(1000);
});

it('calculates cents until next claim after some claims', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'threshold_cents' => 4000,
        'is_repeating' => true,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 5000,
    ]);

    AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    $service = app(RewardThresholdService::class);

    // Has 1 claim, so next milestone is 2 * 4000 = 8000, remaining = 3000
    expect($service->centsUntilNextClaim($profile, $threshold))->toBe(3000);
});

it('returns zero cents remaining for exhausted non-repeating threshold', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->nonRepeating()->create([
        'project_id' => $project->id,
        'threshold_cents' => 8000,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 20000,
    ]);

    AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    $service = app(RewardThresholdService::class);

    expect($service->centsUntilNextClaim($profile, $threshold))->toBe(0);
});

it('claims a reward and creates a claim row', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'threshold_cents' => 4000,
        'is_repeating' => true,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 5000,
    ]);

    $service = app(RewardThresholdService::class);
    $claim = $service->claimReward($profile, $threshold);

    expect($claim)->not->toBeNull()
        ->and($claim->audience_profile_id)->toBe($profile->id)
        ->and($claim->reward_threshold_id)->toBe($threshold->id);

    $this->assertDatabaseCount('audience_reward_claims', 1);
});

it('returns null when claiming an ineligible reward', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'threshold_cents' => 4000,
        'is_repeating' => true,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 2000,
    ]);

    $service = app(RewardThresholdService::class);
    $claim = $service->claimReward($profile, $threshold);

    expect($claim)->toBeNull();
    $this->assertDatabaseCount('audience_reward_claims', 0);
});

it('returns claimable rewards across multiple thresholds', function () {
    $project = Project::factory()->create();
    $project->rewardThresholds()->delete();
    $threshold1 = RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'threshold_cents' => 4000,
        'is_repeating' => true,
        'sort_order' => 0,
    ]);
    $threshold2 = RewardThreshold::factory()->nonRepeating()->create([
        'project_id' => $project->id,
        'threshold_cents' => 8000,
        'reward_type' => 'free_cd',
        'reward_label' => 'Free CD',
        'sort_order' => 1,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 10000,
    ]);

    $project->load('rewardThresholds');

    $service = app(RewardThresholdService::class);
    $claimable = $service->claimableRewards($profile, $project);

    expect($claimable)->toHaveCount(2)
        ->and($claimable[0]['available_claims'])->toBe(2)
        ->and($claimable[1]['available_claims'])->toBe(1);
});

// --- RewardThresholdController ---

it('lists reward thresholds for a project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $project->rewardThresholds()->delete();
    RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'threshold_cents' => 4000,
        'sort_order' => 0,
    ]);
    RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'threshold_cents' => 8000,
        'reward_type' => 'free_cd',
        'reward_label' => 'Free CD',
        'sort_order' => 1,
    ]);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/me/projects/{$project->id}/reward-thresholds")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.threshold_cents', 4000)
        ->assertJsonPath('data.1.threshold_cents', 8000);
});

it('creates a reward threshold', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $project->rewardThresholds()->delete();

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/me/projects/{$project->id}/reward-thresholds", [
        'threshold_cents' => 5000,
        'reward_type' => 'free_request',
        'reward_label' => 'Free Song Request',
        'is_repeating' => true,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.threshold_cents', 5000)
        ->assertJsonPath('data.reward_type', 'free_request')
        ->assertJsonPath('data.is_repeating', true);

    $this->assertDatabaseCount('reward_thresholds', 1);
});

it('normalizes threshold_cents to whole dollars', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/me/projects/{$project->id}/reward-thresholds", [
        'threshold_cents' => 4050,
        'reward_type' => 'free_request',
        'reward_label' => 'Free Song Request',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.threshold_cents', 4100);
});

it('updates a reward threshold', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $threshold = RewardThreshold::factory()->create([
        'project_id' => $project->id,
    ]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/me/projects/{$project->id}/reward-thresholds/{$threshold->id}", [
        'reward_label' => 'Updated Label',
        'is_repeating' => false,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.reward_label', 'Updated Label')
        ->assertJsonPath('data.is_repeating', false);
});

it('deletes a reward threshold', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $project->rewardThresholds()->delete();
    $threshold = RewardThreshold::factory()->create([
        'project_id' => $project->id,
    ]);

    Sanctum::actingAs($owner);

    $this->deleteJson("/api/v1/me/projects/{$project->id}/reward-thresholds/{$threshold->id}")
        ->assertSuccessful();

    $this->assertDatabaseCount('reward_thresholds', 0);
});

it('reorders reward thresholds', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $project->rewardThresholds()->delete();
    $t1 = RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'sort_order' => 0,
        'reward_label' => 'First',
    ]);
    $t2 = RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'sort_order' => 1,
        'reward_label' => 'Second',
    ]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/me/projects/{$project->id}/reward-thresholds/reorder", [
        'ids' => [$t2->id, $t1->id],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.0.reward_label', 'Second')
        ->assertJsonPath('data.1.reward_label', 'First');
});

it('rejects non-owner from creating reward thresholds', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    Sanctum::actingAs($otherUser);

    $this->postJson("/api/v1/me/projects/{$project->id}/reward-thresholds", [
        'threshold_cents' => 4000,
        'reward_type' => 'free_request',
        'reward_label' => 'Free Request',
    ])->assertForbidden();
});

it('rejects accessing thresholds from another project', function () {
    $owner = User::factory()->create();
    $project1 = Project::factory()->create(['owner_user_id' => $owner->id]);
    $project2 = Project::factory()->create(['owner_user_id' => $owner->id]);
    $threshold = RewardThreshold::factory()->create([
        'project_id' => $project1->id,
    ]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/me/projects/{$project2->id}/reward-thresholds/{$threshold->id}", [
        'reward_label' => 'Hacked',
    ])->assertNotFound();
});

it('includes reward_thresholds in the project resource', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $project->rewardThresholds()->delete();
    RewardThreshold::factory()->create([
        'project_id' => $project->id,
        'threshold_cents' => 4000,
    ]);

    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/me/projects')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.0.reward_thresholds')
        ->assertJsonPath('data.0.reward_thresholds.0.threshold_cents', 4000);
});

// --- Default free-request reward threshold on project creation ---

it('auto-creates the default free-request reward threshold when a project is created', function () {
    $project = Project::factory()->create();

    $thresholds = $project->rewardThresholds()->get();

    expect($thresholds)->toHaveCount(1);
    expect($thresholds->first())
        ->reward_type->toBe(RewardThreshold::TYPE_FREE_REQUEST)
        ->threshold_cents->toBe(RewardThreshold::DEFAULT_FREE_REQUEST_THRESHOLD_CENTS)
        ->reward_label->toBe(RewardThreshold::DEFAULT_FREE_REQUEST_LABEL)
        ->reward_icon->toBe(RewardThreshold::DEFAULT_FREE_REQUEST_ICON)
        ->is_repeating->toBeTrue()
        ->sort_order->toBe(0);
});

it('includes the default threshold when a new project is created via API', function () {
    $owner = User::factory()->create();
    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/v1/me/projects', [
        'name' => 'New Performer Project',
    ])->assertCreated();

    $response
        ->assertJsonCount(1, 'project.reward_thresholds')
        ->assertJsonPath('project.reward_thresholds.0.reward_type', 'free_request')
        ->assertJsonPath('project.reward_thresholds.0.threshold_cents', 4000)
        ->assertJsonPath('project.reward_thresholds.0.reward_icon', 'music_note')
        ->assertJsonPath('project.reward_thresholds.0.reward_icon_emoji', '🎵');
});

// --- Reward icon + description support ---

it('accepts a curated reward_icon code and description when creating a threshold', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $project->rewardThresholds()->delete();

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/me/projects/{$project->id}/reward-thresholds", [
        'threshold_cents' => 10000,
        'reward_type' => 'free_cd',
        'reward_label' => 'Free CD',
        'reward_icon' => 'album',
        'reward_description' => 'Come up to the stage after the show.',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.reward_icon', 'album')
        ->assertJsonPath('data.reward_icon_emoji', '💿')
        ->assertJsonPath('data.reward_description', 'Come up to the stage after the show.');
});

it('rejects an unknown reward_icon code', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/me/projects/{$project->id}/reward-thresholds", [
        'threshold_cents' => 5000,
        'reward_type' => 'custom',
        'reward_label' => 'Mystery Reward',
        'reward_icon' => 'not_a_real_icon',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reward_icon');
});

it('rejects a reward_description longer than 500 characters', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/me/projects/{$project->id}/reward-thresholds", [
        'threshold_cents' => 5000,
        'reward_type' => 'custom',
        'reward_label' => 'Mystery Reward',
        'reward_description' => str_repeat('a', 501),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reward_description');
});

it('updates reward_icon and reward_description on an existing threshold', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $threshold = $project->rewardThresholds()->first();

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/me/projects/{$project->id}/reward-thresholds/{$threshold->id}", [
        'reward_icon' => 'star',
        'reward_description' => 'A VIP shoutout during the next song.',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.reward_icon', 'star')
        ->assertJsonPath('data.reward_icon_emoji', '⭐')
        ->assertJsonPath('data.reward_description', 'A VIP shoutout during the next song.');
});

// --- Pending vs delivered claim semantics ---

it('scopes pending and delivered claims correctly', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->create(['project_id' => $project->id]);
    $profile = AudienceProfile::factory()->create(['project_id' => $project->id]);

    $delivered = AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);
    $pending = AudienceRewardClaim::factory()->pending()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    expect(AudienceRewardClaim::query()->pending()->pluck('id')->all())
        ->toBe([$pending->id])
        ->and(AudienceRewardClaim::query()->delivered()->pluck('id')->all())
        ->toBe([$delivered->id]);
});

it('does not count pending claims against repeating threshold availability', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->withType('free_cd', 'Free CD')->create([
        'project_id' => $project->id,
        'threshold_cents' => 4000,
        'is_repeating' => true,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 8000,
    ]);

    AudienceRewardClaim::factory()->pending()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    $service = app(RewardThresholdService::class);

    // 8000 / 4000 = 2 earned, 0 delivered, 2 available (pending doesn't count)
    expect($service->availableClaims($profile, $threshold))->toBe(2);
});

it('does not count pending claims against non-repeating threshold availability', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->nonRepeating()->withType('free_cd', 'Free CD')->create([
        'project_id' => $project->id,
        'threshold_cents' => 8000,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 10000,
    ]);

    AudienceRewardClaim::factory()->pending()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    $service = app(RewardThresholdService::class);

    // Pending claim doesn't exhaust the non-repeating threshold yet.
    expect($service->availableClaims($profile, $threshold))->toBe(1);
});

it('counts delivered claims against threshold availability after handover', function () {
    $project = Project::factory()->create();
    $threshold = RewardThreshold::factory()->nonRepeating()->withType('free_cd', 'Free CD')->create([
        'project_id' => $project->id,
        'threshold_cents' => 8000,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'cumulative_tip_cents' => 10000,
    ]);

    AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    $service = app(RewardThresholdService::class);

    expect($service->availableClaims($profile, $threshold))->toBe(0);
});

// --- POST /me/reward-claims/{rewardClaim}/delivered ---

it('marks a pending reward claim as delivered', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $threshold = RewardThreshold::factory()
        ->withType('free_cd', 'Free CD')
        ->create(['project_id' => $project->id]);
    $profile = AudienceProfile::factory()->create(['project_id' => $project->id]);
    $claim = AudienceRewardClaim::factory()->pending()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/v1/me/reward-claims/{$claim->id}/delivered");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Reward marked as delivered.')
        ->assertJsonPath('reward_claim.id', $claim->id);

    $claim->refresh();
    expect($claim->claimed_at)->not->toBeNull();
});

it('is idempotent when marking an already-delivered reward claim', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $threshold = RewardThreshold::factory()
        ->withType('free_cd', 'Free CD')
        ->create(['project_id' => $project->id]);
    $profile = AudienceProfile::factory()->create(['project_id' => $project->id]);
    $deliveredAt = now()->subHour();
    $claim = AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
        'claimed_at' => $deliveredAt,
    ]);
    $originalClaimedAtTimestamp = $claim->fresh()->claimed_at->getTimestamp();

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/me/reward-claims/{$claim->id}/delivered")
        ->assertSuccessful();

    $claim->refresh();
    expect($claim->claimed_at->getTimestamp())->toBe($originalClaimedAtTimestamp);
});

it('rejects marking delivered for a reward claim outside the user\'s projects', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $threshold = RewardThreshold::factory()
        ->withType('free_cd', 'Free CD')
        ->create(['project_id' => $project->id]);
    $profile = AudienceProfile::factory()->create(['project_id' => $project->id]);
    $claim = AudienceRewardClaim::factory()->pending()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    Sanctum::actingAs($other);

    $this->postJson("/api/v1/me/reward-claims/{$claim->id}/delivered")
        ->assertNotFound();

    $claim->refresh();
    expect($claim->claimed_at)->toBeNull();
});
