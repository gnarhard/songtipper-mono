<?php

declare(strict_types=1);

use App\Models\AudienceProfile;
use App\Models\AudienceRewardClaim;
use App\Models\Project;
use App\Models\Request;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

it('belongs to a project', function () {
    $model = new AudienceProfile;
    $relation = $model->project();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
});

it('has many requests', function () {
    $model = new AudienceProfile;
    $relation = $model->requests();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Request::class);
});

it('has many reward claims', function () {
    $model = new AudienceProfile;
    $relation = $model->rewardClaims();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(AudienceRewardClaim::class);
});

// --- hasEarnedFreeRequest ---

it('returns true when cumulative tips meet the threshold', function () {
    $profile = AudienceProfile::factory()->create([
        'cumulative_tip_cents' => 5000,
    ]);

    expect($profile->hasEarnedFreeRequest(5000))->toBeTrue();
});

it('returns true when cumulative tips exceed the threshold', function () {
    $profile = AudienceProfile::factory()->create([
        'cumulative_tip_cents' => 7500,
    ]);

    expect($profile->hasEarnedFreeRequest(5000))->toBeTrue();
});

it('returns false when cumulative tips are below the threshold', function () {
    $profile = AudienceProfile::factory()->create([
        'cumulative_tip_cents' => 4999,
    ]);

    expect($profile->hasEarnedFreeRequest(5000))->toBeFalse();
});

it('returns false when threshold is zero', function () {
    $profile = AudienceProfile::factory()->create([
        'cumulative_tip_cents' => 10000,
    ]);

    expect($profile->hasEarnedFreeRequest(0))->toBeFalse();
});

it('returns false when cumulative tips are zero', function () {
    $profile = AudienceProfile::factory()->create([
        'cumulative_tip_cents' => 0,
    ]);

    expect($profile->hasEarnedFreeRequest(5000))->toBeFalse();
});

// --- centsUntilFreeRequest ---

it('returns zero when cumulative tips meet the threshold', function () {
    $profile = AudienceProfile::factory()->create([
        'cumulative_tip_cents' => 5000,
    ]);

    expect($profile->centsUntilFreeRequest(5000))->toBe(0);
});

it('returns zero when cumulative tips exceed the threshold', function () {
    $profile = AudienceProfile::factory()->create([
        'cumulative_tip_cents' => 8000,
    ]);

    expect($profile->centsUntilFreeRequest(5000))->toBe(0);
});

it('returns the remaining cents until the threshold', function () {
    $profile = AudienceProfile::factory()->create([
        'cumulative_tip_cents' => 3000,
    ]);

    expect($profile->centsUntilFreeRequest(5000))->toBe(2000);
});

it('returns the full threshold when no tips have been given', function () {
    $profile = AudienceProfile::factory()->create([
        'cumulative_tip_cents' => 0,
    ]);

    expect($profile->centsUntilFreeRequest(5000))->toBe(5000);
});

// --- Casts ---

it('casts cumulative_tip_cents as integer', function () {
    $profile = AudienceProfile::factory()->create([
        'cumulative_tip_cents' => 2500,
    ]);

    expect($profile->refresh()->cumulative_tip_cents)->toBeInt();
});

it('casts last_seen_at as datetime', function () {
    $profile = AudienceProfile::factory()->create([
        'last_seen_at' => '2026-03-15 10:30:00',
    ]);

    expect($profile->refresh()->last_seen_at)->toBeInstanceOf(Carbon::class);
});
