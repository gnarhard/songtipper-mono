<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('exposes project entitlements and account usage for any owner', function () {
    $owner = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => false,
        'is_accepting_tips' => false,
    ]);

    Sanctum::actingAs($owner);

    $projectsResponse = $this->getJson('/api/v1/me/projects');

    $projectsResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $project->id)
        ->assertJsonPath('data.0.entitlements.plan_code', User::BILLING_PLAN_PRO_MONTHLY)
        ->assertJsonPath('data.0.entitlements.plan_tier', 'pro')
        ->assertJsonPath('data.0.entitlements.repertoire_song_limit', null)
        ->assertJsonPath('data.0.entitlements.single_chart_upload_limit_bytes', 2 * 1024 * 1024)
        ->assertJsonPath('data.0.entitlements.bulk_chart_upload_limit_bytes', 2 * 1024 * 1024)
        ->assertJsonPath('data.0.entitlements.bulk_chart_file_limit', 20)
        ->assertJsonPath('data.0.entitlements.ai_interactive_per_minute', 30)
        ->assertJsonPath('data.0.entitlements.bulk_ai_window_limit', 500)
        ->assertJsonPath('data.0.entitlements.bulk_ai_window_hours', 6)
        ->assertJsonPath('data.0.entitlements.can_use_public_requests', true)
        ->assertJsonPath('data.0.entitlements.can_access_queue', true)
        ->assertJsonPath('data.0.entitlements.can_access_history', true)
        ->assertJsonPath('data.0.entitlements.can_view_owner_stats', true)
        ->assertJsonPath('data.0.entitlements.can_view_wallet', true);

    $usageResponse = $this->getJson('/api/v1/me/usage');

    $usageResponse->assertOk()
        ->assertJsonPath('data.plan.code', User::BILLING_PLAN_PRO_MONTHLY)
        ->assertJsonPath('data.plan.tier', 'pro')
        ->assertJsonPath('data.storage.used_bytes', 0)
        ->assertJsonPath('data.storage.status', 'ok')
        ->assertJsonPath('data.ai.operations_used', 0)
        ->assertJsonPath('data.ai.status', 'ok')
        ->assertJsonPath('data.ai.bulk_window_limit', 500)
        ->assertJsonPath('data.ai.bulk_window_hours', 6)
        ->assertJsonPath('data.review.state', 'clear')
        ->assertJsonPath('data.review.reason', null)
        ->assertJsonPath('data.warnings', []);
});

it('does not reject enabling requests based on plan tier since all users are pro', function () {
    $owner = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => false,
        'is_accepting_tips' => false,
    ]);

    Sanctum::actingAs($owner);

    // Enabling only requests (without tips) should pass the feature check
    // but may require payout setup when both are true
    $response = $this->putJson("/api/v1/me/projects/{$project->id}", [
        'is_accepting_requests' => true,
    ]);

    $response->assertOk();
    expect($project->fresh()?->is_accepting_requests)->toBeTrue();
});

it('allows all feature endpoints for any owned project', function () {
    $owner = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'pro-owned-project',
        'is_accepting_requests' => true,
        'is_accepting_tips' => true,
    ]);

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/me/projects/{$project->id}/queue")
        ->assertSuccessful();

    $this->getJson("/api/v1/me/projects/{$project->id}/requests/history")
        ->assertSuccessful();

    $this->getJson("/api/v1/me/projects/{$project->id}/stats?timezone=America/Denver&preset=today")
        ->assertSuccessful();
});
