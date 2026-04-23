<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use App\Services\SongMetadataAiProvider;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
});

it('extracts songs from image and imports them', function () {
    $mockProvider = Mockery::mock(SongMetadataAiProvider::class);
    $mockProvider->shouldReceive('extractSongsFromImage')
        ->once()
        ->andReturn([
            ['title' => 'Bohemian Rhapsody', 'artist' => 'Queen', 'energy_level' => 'high', 'genre' => 'Rock', 'theme' => 'story', 'era' => '1970s'],
            ['title' => 'Yesterday', 'artist' => 'The Beatles', 'energy_level' => 'low', 'genre' => 'Pop', 'theme' => 'love'],
        ]);
    $mockProvider->shouldReceive('provider')->andReturn('anthropic');
    $this->app->instance(SongMetadataAiProvider::class, $mockProvider);

    Sanctum::actingAs($this->owner);

    $image = UploadedFile::fake()->image('setlist.jpg', 800, 600);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/import-from-image", [
        'image' => $image,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('extracted', 2)
        ->assertJsonPath('imported', 2)
        ->assertJsonPath('duplicates', 0)
        ->assertJsonPath('limit_reached', 0);

    expect(Song::count())->toBe(2);
    expect(ProjectSong::count())->toBe(2);

    $bohemian = Song::where('title', 'Bohemian Rhapsody')->first();
    expect($bohemian)->not->toBeNull();
    expect($bohemian->energy_level?->value)->toBe('high');
    expect($bohemian->genre)->toBe('Rock');
    expect($bohemian->theme)->toBe('story');
    expect($bohemian->era)->not->toBeNull();
});

it('skips duplicate songs already in repertoire', function () {
    $existingSong = Song::findOrCreateByTitleAndArtist('Bohemian Rhapsody', 'Queen');
    ProjectSong::query()->create([
        'project_id' => $this->project->id,
        'song_id' => $existingSong->id,
    ]);

    $mockProvider = Mockery::mock(SongMetadataAiProvider::class);
    $mockProvider->shouldReceive('extractSongsFromImage')
        ->once()
        ->andReturn([
            ['title' => 'Bohemian Rhapsody', 'artist' => 'Queen'],
            ['title' => 'Yesterday', 'artist' => 'The Beatles'],
        ]);
    $mockProvider->shouldReceive('provider')->andReturn('anthropic');
    $this->app->instance(SongMetadataAiProvider::class, $mockProvider);

    Sanctum::actingAs($this->owner);

    $image = UploadedFile::fake()->image('setlist.jpg', 800, 600);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/import-from-image", [
        'image' => $image,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('extracted', 2)
        ->assertJsonPath('imported', 1)
        ->assertJsonPath('duplicates', 1);

    expect(ProjectSong::count())->toBe(2);

    $songs = $response->json('songs');
    $duplicateSong = collect($songs)->firstWhere('action', 'duplicate');
    expect($duplicateSong['title'])->toBe('Bohemian Rhapsody');
});

it('returns appropriate message when no songs are found', function () {
    $mockProvider = Mockery::mock(SongMetadataAiProvider::class);
    $mockProvider->shouldReceive('extractSongsFromImage')
        ->once()
        ->andReturn(null);
    $mockProvider->shouldReceive('provider')->andReturn('anthropic');
    $this->app->instance(SongMetadataAiProvider::class, $mockProvider);

    Sanctum::actingAs($this->owner);

    $image = UploadedFile::fake()->image('empty.jpg', 800, 600);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/import-from-image", [
        'image' => $image,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('extracted', 0)
        ->assertJsonPath('imported', 0)
        ->assertJsonPath('message', 'No songs found in image.');
});

it('rejects invalid image types', function () {
    Sanctum::actingAs($this->owner);

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/import-from-image", [
        'image' => $file,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);
});

it('rejects requests without an image', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/import-from-image");

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);
});

it('denies access to non-project members', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $image = UploadedFile::fake()->image('setlist.jpg', 800, 600);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/import-from-image", [
        'image' => $image,
    ]);

    $response->assertNotFound();
});

it('does not overwrite metadata on existing songs', function () {
    $existingSong = Song::findOrCreateByTitleAndArtist('Bohemian Rhapsody', 'Queen');
    $existingSong->update(['energy_level' => 'medium', 'genre' => 'Pop']);

    $mockProvider = Mockery::mock(SongMetadataAiProvider::class);
    $mockProvider->shouldReceive('extractSongsFromImage')
        ->once()
        ->andReturn([
            ['title' => 'Bohemian Rhapsody', 'artist' => 'Queen', 'energy_level' => 'high', 'genre' => 'Rock'],
        ]);
    $mockProvider->shouldReceive('provider')->andReturn('anthropic');
    $this->app->instance(SongMetadataAiProvider::class, $mockProvider);

    Sanctum::actingAs($this->owner);

    $image = UploadedFile::fake()->image('setlist.jpg', 800, 600);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/import-from-image", [
        'image' => $image,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('imported', 1);

    $existingSong->refresh();
    expect($existingSong->energy_level?->value)->toBe('medium');
    expect($existingSong->genre)->toBe('Pop');
});
