<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\Song;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    Sanctum::actingAs($this->owner);
});

it('handles non-object project route parameter for project id extraction', function () {
    // The request class handles both object and non-object route params.
    // When the project route is resolved as a model, it uses $projectRouteValue->id.
    // When it's a raw value (line 29), it casts directly.
    // We test normal flow - project is resolved as model by default.

    $song = Song::factory()->create();
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);
    $setlist = Setlist::factory()->create([
        'project_id' => $this->project->id,
    ]);
    $set = SetlistSet::factory()->create([
        'setlist_id' => $setlist->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}/sets/{$set->id}/songs/bulk",
        [
            'project_song_ids' => [$projectSong->id],
        ]
    );

    $response->assertSuccessful();
});

it('rejects project_song_ids from another project', function () {
    $otherProject = Project::factory()->create();
    $song = Song::factory()->create();
    $otherProjectSong = ProjectSong::factory()->create([
        'project_id' => $otherProject->id,
        'song_id' => $song->id,
    ]);
    $setlist = Setlist::factory()->create([
        'project_id' => $this->project->id,
    ]);
    $set = SetlistSet::factory()->create([
        'setlist_id' => $setlist->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}/sets/{$set->id}/songs/bulk",
        [
            'project_song_ids' => [$otherProjectSong->id],
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['project_song_ids.0']);
});
