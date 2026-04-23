<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('allows a pro owner to invite a member', function () {
    $owner = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
    ]);
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $invitee = User::factory()->create();

    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/v1/me/projects/{$project->id}/members", [
        'email' => $invitee->email,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Project member invited successfully.');
});

it('allows any owner to invite a member regardless of plan', function () {
    $owner = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $invitee = User::factory()->create();

    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/v1/me/projects/{$project->id}/members", [
        'email' => $invitee->email,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Project member invited successfully.');
});
