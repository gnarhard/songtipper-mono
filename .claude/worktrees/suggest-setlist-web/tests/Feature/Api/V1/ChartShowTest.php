<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\Project;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $this->song = Song::factory()->create();

    Sanctum::actingAs($this->owner);
});

it('returns chart details for an owned chart', function () {
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
        'has_renders' => true,
        'page_count' => 3,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}");

    $response->assertOk()
        ->assertJsonPath('chart.id', $chart->id)
        ->assertJsonPath('chart.project_id', $this->project->id)
        ->assertJsonPath('chart.song.id', $this->song->id)
        ->assertJsonPath('chart.page_count', 3)
        ->assertJsonPath('chart.has_renders', true)
        ->assertJsonPath(
            'chart.updated_at',
            $chart->updated_at?->toIso8601String(),
        );
});

it('returns 403 when accessing another users chart', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->create([
        'owner_user_id' => $otherUser->id,
    ]);
    $otherSong = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $otherUser->id,
        'project_id' => $otherProject->id,
        'song_id' => $otherSong->id,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}");

    $response->assertForbidden();
});

it('lists all charts for the authenticated user', function () {
    $songs = Song::factory()->count(3)->create();
    $songs->each(function ($song) {
        Chart::factory()->create([
            'owner_user_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'song_id' => $song->id,
        ]);
    });

    $response = $this->getJson('/api/v1/me/charts');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('filters charts by project_id', function () {
    Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
    ]);

    $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
    Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $otherProject->id,
        'song_id' => $this->song->id,
    ]);

    $response = $this->getJson("/api/v1/me/charts?project_id={$this->project->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters charts by song_id', function () {
    Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
    ]);

    $otherSong = Song::factory()->create();
    Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'song_id' => $otherSong->id,
    ]);

    $response = $this->getJson("/api/v1/me/charts?song_id={$this->song->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns signed url for own chart', function () {
    config()->set('filesystems.chart', 'local');
    Storage::fake('local');

    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
        'storage_disk' => 'local',
    ]);

    Storage::disk('local')->put($chart->storage_path_pdf, 'pdf-content');

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/signed-url");

    $response->assertOk()
        ->assertJsonStructure(['url']);
});

it('returns 403 when requesting signed url for another users chart', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->create(['owner_user_id' => $otherUser->id]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $otherUser->id,
        'project_id' => $otherProject->id,
        'song_id' => $this->song->id,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/signed-url");

    $response->assertForbidden();
});

it('deletes own chart', function () {
    config()->set('filesystems.chart', 'local');
    Storage::fake('local');

    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
        'storage_disk' => 'local',
    ]);

    $response = $this->deleteJson("/api/v1/me/charts/{$chart->id}");

    $response->assertOk()
        ->assertJsonPath('message', 'Chart deleted successfully.');

    $this->assertDatabaseMissing('charts', ['id' => $chart->id]);
});

it('returns 403 when deleting another users chart', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->create(['owner_user_id' => $otherUser->id]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $otherUser->id,
        'project_id' => $otherProject->id,
        'song_id' => $this->song->id,
    ]);

    $response = $this->deleteJson("/api/v1/me/charts/{$chart->id}");

    $response->assertForbidden();
});

it('returns 403 when rendering another users chart', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->create(['owner_user_id' => $otherUser->id]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $otherUser->id,
        'project_id' => $otherProject->id,
        'song_id' => $this->song->id,
    ]);

    $response = $this->postJson("/api/v1/me/charts/{$chart->id}/render");

    $response->assertForbidden();
});
