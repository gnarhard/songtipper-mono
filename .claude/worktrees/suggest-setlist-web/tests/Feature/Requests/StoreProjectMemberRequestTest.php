<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    Sanctum::actingAs($this->owner);
});

it('normalizes non-string email gracefully', function () {
    // When email is not a string (e.g., integer), prepareForValidation returns early
    // and validation catches the email as invalid
    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/members",
        ['email' => 12345]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
