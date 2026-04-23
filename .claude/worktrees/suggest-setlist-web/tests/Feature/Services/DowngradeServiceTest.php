<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use App\Services\BillingActivationService;

it('does not disable requests or tips because all users get pro features', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);

    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
        'is_accepting_requests' => true,
        'is_accepting_tips' => true,
    ]);

    $service = app(BillingActivationService::class);
    $service->enforceDowngradeLimits($user);

    $project->refresh();
    expect($project->is_accepting_requests)->toBeTrue()
        ->and($project->is_accepting_tips)->toBeTrue();
});

it('does not change projects for pro users', function () {
    $user = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
    ]);

    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
        'is_accepting_requests' => true,
        'is_accepting_tips' => true,
    ]);

    $service = app(BillingActivationService::class);
    $service->enforceDowngradeLimits($user);

    $project->refresh();
    expect($project->is_accepting_requests)->toBeTrue()
        ->and($project->is_accepting_tips)->toBeTrue();
});

it('leaves already-disabled requests and tips unchanged', function () {
    $user = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
    ]);

    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
        'is_accepting_requests' => false,
        'is_accepting_tips' => false,
    ]);

    $service = app(BillingActivationService::class);
    $service->enforceDowngradeLimits($user);

    $project->refresh();
    expect($project->is_accepting_requests)->toBeFalse()
        ->and($project->is_accepting_tips)->toBeFalse();
});

it('preserves project data and does not delete projects', function () {
    $user = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
    ]);

    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
        'name' => 'My Band',
        'is_accepting_requests' => true,
        'is_accepting_tips' => true,
    ]);

    $service = app(BillingActivationService::class);
    $service->enforceDowngradeLimits($user);

    $project->refresh();
    expect($project->name)->toBe('My Band')
        ->and($project->exists)->toBeTrue()
        ->and($project->is_accepting_requests)->toBeTrue()
        ->and($project->is_accepting_tips)->toBeTrue();
});
