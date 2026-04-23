<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('allows a free user to create a project', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/me/projects', [
        'name' => 'My First Project',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Project created successfully.');
});

it('allows a free user to create multiple projects since limits are unlimited', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);
    Project::factory()->create(['owner_user_id' => $user->id]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/me/projects', [
        'name' => 'Second Project',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Project created successfully.');
});

it('allows any user to create unlimited projects', function () {
    $user = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
    ]);

    Sanctum::actingAs($user);

    for ($i = 1; $i <= 5; $i++) {
        $response = $this->postJson('/api/v1/me/projects', [
            'name' => "Project {$i}",
        ]);

        $response->assertStatus(201);
    }

    expect($user->ownedProjects()->count())->toBe(5);
});

it('allows a pro user to create unlimited projects', function () {
    $user = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
    ]);
    Project::factory()->count(5)->create(['owner_user_id' => $user->id]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/me/projects', [
        'name' => 'Another Project',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Project created successfully.');

    expect($user->ownedProjects()->count())->toBe(6);
});

it('returns null project_limit in entitlements', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/me/projects', [
        'name' => 'Test Project',
    ]);

    $response->assertStatus(201);

    $projectsResponse = $this->getJson('/api/v1/me/projects');
    $projectsResponse->assertOk()
        ->assertJsonPath('data.0.entitlements.project_limit', null);
});
