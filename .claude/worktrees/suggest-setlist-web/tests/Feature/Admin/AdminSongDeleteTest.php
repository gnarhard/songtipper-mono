<?php

declare(strict_types=1);

use App\Models\AdminDesignation;
use App\Models\Chart;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    AdminDesignation::create(['email' => $this->admin->email]);
    $this->actingAs($this->admin);

    $this->project = Project::factory()->create(['owner_user_id' => $this->admin->id]);
});

it('preserves referencing project songs when admin chooses the preserve option from the merge modal', function () {
    $song = Song::factory()->create(['title' => 'Preserve Case', 'artist' => 'Admin']);
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->admin->id,
        'song_id' => $song->id,
    ]);

    Livewire::test('admin-songs-page')
        ->call('confirmDelete', $song->id)
        ->assertSet('showMergeModal', true)
        ->call('forceDeleteFromMergeModal')
        ->assertSet('deleteReferencingProjectSongs', false)
        ->call('deleteSong')
        ->assertSet('errorMessage', null);

    expect(Song::withTrashed()->find($song->id)->trashed())->toBeTrue();
    // Project song is preserved (but now references a soft-deleted song — the
    // existing documented behavior the admin explicitly opted into).
    expect(ProjectSong::find($projectSong->id))->not->toBeNull();
});

it('cascade deletes referencing project songs when admin chooses the remove-references option', function () {
    $song = Song::factory()->create(['title' => 'Cascade Case', 'artist' => 'Admin']);

    $firstProjectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->admin->id,
        'song_id' => $song->id,
    ]);

    $otherOwner = User::factory()->create();
    $otherProject = Project::factory()->create(['owner_user_id' => $otherOwner->id]);
    $secondProjectSong = ProjectSong::factory()->create([
        'project_id' => $otherProject->id,
        'user_id' => $otherOwner->id,
        'song_id' => $song->id,
    ]);

    // A chart bound to the first project song — the FK cascade from
    // project_songs should hard-delete it too.
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->admin->id,
        'project_id' => $this->project->id,
        'project_song_id' => $firstProjectSong->id,
        'song_id' => $song->id,
    ]);

    Livewire::test('admin-songs-page')
        ->call('confirmDelete', $song->id)
        ->assertSet('showMergeModal', true)
        ->call('forceDeleteWithReferencesFromMergeModal')
        ->assertSet('deleteReferencingProjectSongs', true)
        ->call('deleteSong')
        ->assertSet('errorMessage', null);

    expect(Song::withTrashed()->find($song->id)->trashed())->toBeTrue();
    expect(ProjectSong::find($firstProjectSong->id))->toBeNull();
    expect(ProjectSong::find($secondProjectSong->id))->toBeNull();
    expect(Chart::find($chart->id))->toBeNull();
});

it('routes direct delete of a song with no references to the delete confirmation modal', function () {
    $song = Song::factory()->create(['title' => 'No Refs', 'artist' => 'Admin']);

    Livewire::test('admin-songs-page')
        ->call('confirmDelete', $song->id)
        ->assertSet('showMergeModal', false)
        ->assertSet('showDeleteConfirm', true)
        ->assertSet('deleteReferencingProjectSongs', false)
        ->call('deleteSong');

    expect(Song::withTrashed()->find($song->id)->trashed())->toBeTrue();
});
