<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistSong;
use App\Models\Song;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $this->song = Song::factory()->create();
    $this->projectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
        'performance_count' => 0,
    ]);
    Sanctum::actingAs($this->owner);
});

it('validates setlist_id belongs to the project', function () {
    $otherProject = Project::factory()->create();
    $otherSetlist = Setlist::factory()->create([
        'project_id' => $otherProject->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances",
        [
            'source' => 'setlist',
            'setlist_id' => $otherSetlist->id,
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['setlist_id']);
});

it('validates set_id belongs to the project', function () {
    $otherProject = Project::factory()->create();
    $otherSetlist = Setlist::factory()->create([
        'project_id' => $otherProject->id,
    ]);
    $otherSet = SetlistSet::factory()->create([
        'setlist_id' => $otherSetlist->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances",
        [
            'source' => 'setlist',
            'set_id' => $otherSet->id,
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['set_id']);
});

it('validates setlist_song_id belongs to the project', function () {
    $otherProject = Project::factory()->create();
    $otherSetlist = Setlist::factory()->create([
        'project_id' => $otherProject->id,
    ]);
    $otherSet = SetlistSet::factory()->create([
        'setlist_id' => $otherSetlist->id,
    ]);
    $otherSetlistSong = SetlistSong::factory()->create([
        'set_id' => $otherSet->id,
        'project_song_id' => $this->projectSong->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances",
        [
            'source' => 'setlist',
            'setlist_song_id' => $otherSetlistSong->id,
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['setlist_song_id']);
});

it('validates set belongs to the specified setlist', function () {
    $setlist = Setlist::factory()->create([
        'project_id' => $this->project->id,
    ]);
    $otherSetlist = Setlist::factory()->create([
        'project_id' => $this->project->id,
    ]);
    $set = SetlistSet::factory()->create([
        'setlist_id' => $otherSetlist->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances",
        [
            'source' => 'setlist',
            'setlist_id' => $setlist->id,
            'set_id' => $set->id,
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['set_id']);
});

it('validates setlist_song belongs to the specified set', function () {
    $setlist = Setlist::factory()->create([
        'project_id' => $this->project->id,
    ]);
    $set = SetlistSet::factory()->create([
        'setlist_id' => $setlist->id,
    ]);
    $otherSet = SetlistSet::factory()->create([
        'setlist_id' => $setlist->id,
    ]);
    $setlistSong = SetlistSong::factory()->create([
        'set_id' => $otherSet->id,
        'project_song_id' => $this->projectSong->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances",
        [
            'source' => 'setlist',
            'set_id' => $set->id,
            'setlist_song_id' => $setlistSong->id,
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['setlist_song_id']);
});

it('validates setlist_song belongs to the specified setlist without set', function () {
    $setlist = Setlist::factory()->create([
        'project_id' => $this->project->id,
    ]);
    $otherSetlist = Setlist::factory()->create([
        'project_id' => $this->project->id,
    ]);
    $otherSet = SetlistSet::factory()->create([
        'setlist_id' => $otherSetlist->id,
    ]);
    $setlistSong = SetlistSong::factory()->create([
        'set_id' => $otherSet->id,
        'project_song_id' => $this->projectSong->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances",
        [
            'source' => 'setlist',
            'setlist_id' => $setlist->id,
            'setlist_song_id' => $setlistSong->id,
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['setlist_song_id']);
});

it('validates setlist_song belongs to the correct project song', function () {
    $otherSong = Song::factory()->create();
    $otherProjectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $otherSong->id,
    ]);
    $setlist = Setlist::factory()->create([
        'project_id' => $this->project->id,
    ]);
    $set = SetlistSet::factory()->create([
        'setlist_id' => $setlist->id,
    ]);
    $setlistSong = SetlistSong::factory()->create([
        'set_id' => $set->id,
        'project_song_id' => $otherProjectSong->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances",
        [
            'source' => 'setlist',
            'setlist_song_id' => $setlistSong->id,
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['setlist_song_id']);
});
