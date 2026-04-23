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

it('validates project_song_id is required for skip', function () {
    // Start a performance first so the skip endpoint is available
    $this->postJson("/api/v1/me/projects/{$this->project->id}/performances/start");

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/performances/current/skip", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['project_song_id']);
});

it('validates project_song_id must be an integer for skip', function () {
    $this->postJson("/api/v1/me/projects/{$this->project->id}/performances/start");

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/performances/current/skip", [
        'project_song_id' => 'not-an-integer',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['project_song_id']);
});

it('validates project_song_id must exist in project_songs for skip', function () {
    $this->postJson("/api/v1/me/projects/{$this->project->id}/performances/start");

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/performances/current/skip", [
        'project_song_id' => 999999,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['project_song_id']);
});
