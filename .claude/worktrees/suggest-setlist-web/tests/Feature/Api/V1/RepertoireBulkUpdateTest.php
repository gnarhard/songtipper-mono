<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);

    Sanctum::actingAs($this->owner);
});

it('bulk updates a single whitelisted field', function () {
    $songs = ProjectSong::factory()->count(3)->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'learned' => true,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-update",
        [
            'project_song_ids' => $songs->pluck('id')->all(),
            'fields' => ['learned' => false],
        ],
    );

    $response->assertOk()
        ->assertJsonPath('updated_count', 3)
        ->assertJsonPath('message', 'Updated 3 song(s).');

    foreach ($songs as $song) {
        expect($song->fresh()->learned)->toBeFalse();
    }
});

it('bulk updates multiple whitelisted fields in one request', function () {
    $songs = ProjectSong::factory()->count(2)->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'learned' => true,
        'is_public' => true,
        'mashup' => false,
        'instrumental' => false,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-update",
        [
            'project_song_ids' => $songs->pluck('id')->all(),
            'fields' => [
                'learned' => false,
                'is_public' => false,
                'mashup' => true,
                'instrumental' => true,
            ],
        ],
    );

    $response->assertOk()->assertJsonPath('updated_count', 2);

    foreach ($songs as $song) {
        $fresh = $song->fresh();
        expect($fresh->learned)->toBeFalse();
        expect($fresh->is_public)->toBeFalse();
        expect($fresh->mashup)->toBeTrue();
        expect($fresh->instrumental)->toBeTrue();
    }
});

it('silently skips IDs from another project', function () {
    $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);

    $ownSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'learned' => true,
    ]);
    $foreignSong = ProjectSong::factory()->create([
        'project_id' => $otherProject->id,
        'user_id' => $this->owner->id,
        'learned' => true,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-update",
        [
            'project_song_ids' => [$ownSong->id, $foreignSong->id],
            'fields' => ['learned' => false],
        ],
    );

    $response->assertOk()->assertJsonPath('updated_count', 1);
    expect($ownSong->fresh()->learned)->toBeFalse();
    expect($foreignSong->fresh()->learned)->toBeTrue();
});

it('silently skips IDs owned by another user in the same project', function () {
    $otherUser = User::factory()->create();
    $this->project->members()->attach($otherUser->id);

    $myCopy = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'learned' => true,
    ]);
    $theirCopy = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $otherUser->id,
        'learned' => true,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-update",
        [
            'project_song_ids' => [$myCopy->id, $theirCopy->id],
            'fields' => ['learned' => false],
        ],
    );

    $response->assertOk()->assertJsonPath('updated_count', 1);
    expect($myCopy->fresh()->learned)->toBeFalse();
    expect($theirCopy->fresh()->learned)->toBeTrue();
});

it('returns 422 when fields object is empty', function () {
    $song = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
    ]);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-update",
        [
            'project_song_ids' => [$song->id],
            'fields' => [],
        ],
    )->assertUnprocessable();
});

it('returns 422 when project_song_ids is empty', function () {
    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-update",
        [
            'project_song_ids' => [],
            'fields' => ['learned' => false],
        ],
    )->assertUnprocessable();
});

it('returns 422 when more than 500 IDs are submitted', function () {
    $ids = range(1, 501);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-update",
        [
            'project_song_ids' => $ids,
            'fields' => ['learned' => false],
        ],
    )->assertUnprocessable();
});

it('returns 422 when only unknown fields are supplied', function () {
    $song = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
    ]);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-update",
        [
            'project_song_ids' => [$song->id],
            'fields' => ['title' => 'hacked'],
        ],
    )->assertUnprocessable();
});

it('returns 404 for unauthorized project access', function () {
    $strangerProject = Project::factory()->create();

    $this->postJson(
        "/api/v1/me/projects/{$strangerProject->id}/repertoire/bulk-update",
        [
            'project_song_ids' => [1],
            'fields' => ['learned' => false],
        ],
    )->assertNotFound();
});

it('accurately reports updated_count when some IDs are invalid', function () {
    $validSongs = ProjectSong::factory()->count(2)->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'learned' => true,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-update",
        [
            'project_song_ids' => [
                ...$validSongs->pluck('id')->all(),
                999999, // nonexistent ID
            ],
            'fields' => ['learned' => false],
        ],
    );

    $response->assertOk()->assertJsonPath('updated_count', 2);
});
