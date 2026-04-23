<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectEntitlementService;

it('returns default billing plan when user is null', function () {
    $service = new ProjectEntitlementService;

    $result = $service->resolvedPlanForUser(null);

    expect($result)->toBe(User::defaultBillingPlan());
});

it('returns pro tier for any plan', function () {
    $service = new ProjectEntitlementService;

    expect($service->tierForPlan('free'))->toBe('pro');
    expect($service->tierForPlan('pro_monthly'))->toBe('pro');
    expect($service->tierForPlan('pro_yearly'))->toBe('pro');
    expect($service->tierForPlan('veteran_monthly'))->toBe('pro');
});

it('returns pro tier for any user', function () {
    $service = new ProjectEntitlementService;

    $freeUser = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);

    $proUser = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
    ]);

    expect($service->tierForUser($freeUser))->toBe('pro');
    expect($service->tierForUser($proUser))->toBe('pro');
    expect($service->tierForUser(null))->toBe('pro');
});

it('returns null project limit for all users', function () {
    $service = new ProjectEntitlementService;

    $freeUser = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);

    $proUser = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
    ]);

    expect($service->projectLimitForUser($freeUser))->toBeNull();
    expect($service->projectLimitForUser($proUser))->toBeNull();
});

it('returns false for projectLimitReached for all users', function () {
    $service = new ProjectEntitlementService;

    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);
    Project::factory()->count(10)->create(['owner_user_id' => $user->id]);

    expect($service->projectLimitReached($user))->toBeFalse();
});

it('returns true for canUseFeature for all features except public_requests when card_needed', function () {
    $service = new ProjectEntitlementService;

    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    expect($service->canUseFeature($project, ProjectEntitlementService::FEATURE_BAND_SYNC))->toBeTrue();
    expect($service->canUseFeature($project, ProjectEntitlementService::FEATURE_QUEUE))->toBeTrue();
    expect($service->canUseFeature($project, ProjectEntitlementService::FEATURE_HISTORY))->toBeTrue();
    expect($service->canUseFeature($project, ProjectEntitlementService::FEATURE_OWNER_STATS))->toBeTrue();
    expect($service->canUseFeature($project, ProjectEntitlementService::FEATURE_WALLET))->toBeTrue();
    expect($service->canUseFeature($project, ProjectEntitlementService::FEATURE_PUBLIC_REQUESTS))->toBeTrue();
});

it('returns false for canUseFeature public_requests when owner is card_needed', function () {
    $service = new ProjectEntitlementService;

    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_CARD_NEEDED,
    ]);
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    expect($service->canUseFeature($project, ProjectEntitlementService::FEATURE_PUBLIC_REQUESTS))->toBeFalse();
});

it('includes unlimited project_limit and can_invite_members in entitlementsForProject', function () {
    $service = new ProjectEntitlementService;

    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $entitlements = $service->entitlementsForProject($project, $user);

    expect($entitlements)
        ->toHaveKey('project_limit', null)
        ->toHaveKey('can_invite_members', true)
        ->toHaveKey('plan_tier', 'pro')
        ->toHaveKey('repertoire_song_limit', null);
});
