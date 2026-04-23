<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('exposes correct entitlements for a free owner with earning status', function () {
    $owner = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => false,
        'is_accepting_tips' => false,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/me/projects');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $project->id)
        ->assertJsonPath('data.0.entitlements.plan_code', User::BILLING_PLAN_FREE)
        ->assertJsonPath('data.0.entitlements.plan_tier', 'pro')
        ->assertJsonPath('data.0.entitlements.repertoire_song_limit', null)
        ->assertJsonPath('data.0.entitlements.project_limit', null)
        ->assertJsonPath('data.0.entitlements.can_use_public_requests', true)
        ->assertJsonPath('data.0.entitlements.can_access_queue', true)
        ->assertJsonPath('data.0.entitlements.can_access_history', true)
        ->assertJsonPath('data.0.entitlements.can_view_owner_stats', true)
        ->assertJsonPath('data.0.entitlements.can_view_wallet', true)
        ->assertJsonPath('data.0.entitlements.can_invite_members', true);
});

it('gates public requests for a card_needed owner', function () {
    $owner = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_CARD_NEEDED,
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => true,
        'is_accepting_tips' => true,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/v1/me/projects');

    $response->assertOk()
        ->assertJsonPath('data.0.entitlements.can_use_public_requests', false);
});

it('allows a free owner to enable requests on a project', function () {
    $owner = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => false,
        'is_accepting_tips' => false,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->putJson("/api/v1/me/projects/{$project->id}", [
        'is_accepting_requests' => true,
    ]);

    $response->assertOk();
    expect($project->fresh()->is_accepting_requests)->toBeTrue();
});

it('allows a free owner to enable tips on a project', function () {
    $owner = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => false,
        'is_accepting_tips' => false,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->putJson("/api/v1/me/projects/{$project->id}", [
        'is_accepting_tips' => true,
    ]);

    $response->assertOk();
    expect($project->fresh()->is_accepting_tips)->toBeTrue();
});
