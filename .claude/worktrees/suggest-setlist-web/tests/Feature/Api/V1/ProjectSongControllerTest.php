<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use App\Services\YoutubeVideoResolver;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);

    Sanctum::actingAs($this->owner);
});

it('defaults learned to true when creating a repertoire song', function () {
    $song = Song::factory()->create();

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
        'song_id' => $song->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('project_song.learned', true);

    expect(ProjectSong::query()->where('project_id', $this->project->id)->first()->learned)
        ->toBeTrue();
});

it('accepts learned=false when creating a repertoire song', function () {
    $song = Song::factory()->create();

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
        'song_id' => $song->id,
        'learned' => false,
    ]);

    $response->assertCreated()
        ->assertJsonPath('project_song.learned', false);
});

it('round-trips learned through update', function () {
    $song = Song::factory()->create();
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'song_id' => $song->id,
        'learned' => true,
    ]);

    $this->putJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$projectSong->id}",
        ['learned' => false],
    )->assertOk()->assertJsonPath('project_song.learned', false);

    expect($projectSong->fresh()->learned)->toBeFalse();

    $this->putJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$projectSong->id}",
        ['learned' => true],
    )->assertOk()->assertJsonPath('project_song.learned', true);

    expect($projectSong->fresh()->learned)->toBeTrue();
});

it('filters repertoire list by learned query param', function () {
    $songLearned = Song::factory()->create();
    $songToLearn = Song::factory()->create();

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'song_id' => $songLearned->id,
        'learned' => true,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'song_id' => $songToLearn->id,
        'learned' => false,
    ]);

    $this->getJson("/api/v1/me/projects/{$this->project->id}/repertoire?learned=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.learned', true);

    $this->getJson("/api/v1/me/projects/{$this->project->id}/repertoire?learned=0")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.learned', false);
});

it('returns learned, youtube_video_url, and ultimate_guitar_url in the resource', function () {
    $song = Song::factory()->create([
        'youtube_video_url' => 'https://www.youtube.com/watch?v=abc123',
        'ultimate_guitar_url' => 'https://www.ultimate-guitar.com/search.php?q=x',
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'song_id' => $song->id,
        'learned' => false,
    ]);

    $this->getJson("/api/v1/me/projects/{$this->project->id}/repertoire")
        ->assertOk()
        ->assertJsonPath('data.0.learned', false)
        ->assertJsonPath('data.0.song.youtube_video_url', 'https://www.youtube.com/watch?v=abc123')
        ->assertJsonPath('data.0.song.ultimate_guitar_url', 'https://www.ultimate-guitar.com/search.php?q=x');
});

it('backfills song reference URLs when creating a new song', function () {
    $resolver = Mockery::mock(YoutubeVideoResolver::class);
    $resolver->shouldReceive('resolveMostRelevantVideoUrl')
        ->once()
        ->andReturn('https://www.youtube.com/watch?v=xyz789');
    $this->app->instance(YoutubeVideoResolver::class, $resolver);

    $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
        'title' => 'Brand New Song',
        'artist' => 'Fresh Artist',
    ])->assertCreated();

    $song = Song::query()
        ->where('title', 'Brand New Song')
        ->where('artist', 'Fresh Artist')
        ->first();

    expect($song)->not->toBeNull();
    expect($song->youtube_video_url)->toBe('https://www.youtube.com/watch?v=xyz789');
    expect($song->ultimate_guitar_url)->toContain('ultimate-guitar.com/search.php');
    expect($song->ultimate_guitar_url)->toContain('Brand+New+Song');
});

it('backfills song reference URLs when flipping learned to false', function () {
    $song = Song::factory()->create([
        'title' => 'Old Song',
        'artist' => 'Some Artist',
        'youtube_video_url' => null,
        'ultimate_guitar_url' => null,
    ]);
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'song_id' => $song->id,
        'learned' => true,
    ]);

    $resolver = Mockery::mock(YoutubeVideoResolver::class);
    $resolver->shouldReceive('resolveMostRelevantVideoUrl')
        ->once()
        ->andReturn('https://www.youtube.com/watch?v=found123');
    $this->app->instance(YoutubeVideoResolver::class, $resolver);

    $this->putJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$projectSong->id}",
        ['learned' => false],
    )->assertOk();

    $song->refresh();
    expect($song->youtube_video_url)->toBe('https://www.youtube.com/watch?v=found123');
    expect($song->ultimate_guitar_url)->toContain('ultimate-guitar.com/search.php');
});

it('falls back to YouTube search URL when resolver returns null', function () {
    $resolver = Mockery::mock(YoutubeVideoResolver::class);
    $resolver->shouldReceive('resolveMostRelevantVideoUrl')
        ->once()
        ->andReturn(null);
    $resolver->shouldReceive('buildSearchUrl')
        ->once()
        ->andReturn('https://www.youtube.com/results?search_query=fallback');
    $this->app->instance(YoutubeVideoResolver::class, $resolver);

    $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
        'title' => 'Rare Song',
        'artist' => 'Obscure Artist',
    ])->assertCreated();

    $song = Song::query()
        ->where('title', 'Rare Song')
        ->first();

    expect($song->youtube_video_url)->toBe('https://www.youtube.com/results?search_query=fallback');
});

it('creates a fresh Song row when adding a mashup, even if a catalog song with the same title/artist exists', function () {
    $catalogSong = Song::factory()->create([
        'title' => 'Shared Title',
        'artist' => 'Shared Artist',
    ]);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
        'title' => 'Shared Title',
        'artist' => 'Shared Artist',
        'mashup' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('project_song.mashup', true);

    $projectSong = ProjectSong::query()
        ->where('project_id', $this->project->id)
        ->firstOrFail();

    expect($projectSong->song_id)->not->toBe($catalogSong->id);

    $mashupSong = Song::query()->findOrFail($projectSong->song_id);
    expect($mashupSong->title)->toBe('Shared Title')
        ->and($mashupSong->artist)->toBe('Shared Artist')
        ->and($mashupSong->normalized_key)->toStartWith('mashup:');
});

it('creates distinct Song rows for two mashups with the same title and artist', function () {
    $other = User::factory()->create();
    $otherProject = Project::factory()->create(['owner_user_id' => $other->id]);

    $firstResponse = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
        'title' => 'Jam Session',
        'artist' => 'The Collective',
        'mashup' => true,
    ])->assertCreated();

    Sanctum::actingAs($other);

    $secondResponse = $this->postJson("/api/v1/me/projects/{$otherProject->id}/repertoire", [
        'title' => 'Jam Session',
        'artist' => 'The Collective',
        'mashup' => true,
    ])->assertCreated();

    $firstSongId = $firstResponse->json('project_song.song.id');
    $secondSongId = $secondResponse->json('project_song.song.id');

    expect($firstSongId)->not->toBe($secondSongId);
    expect(Song::query()->whereIn('id', [$firstSongId, $secondSongId])->count())->toBe(2);
});

it('does not overwrite existing song reference URLs on learned=false flip', function () {
    $song = Song::factory()->create([
        'youtube_video_url' => 'https://www.youtube.com/watch?v=existing',
        'ultimate_guitar_url' => 'https://www.ultimate-guitar.com/search.php?q=existing',
    ]);
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'song_id' => $song->id,
        'learned' => true,
    ]);

    $resolver = Mockery::mock(YoutubeVideoResolver::class);
    $resolver->shouldNotReceive('resolveMostRelevantVideoUrl');
    $this->app->instance(YoutubeVideoResolver::class, $resolver);

    $this->putJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$projectSong->id}",
        ['learned' => false],
    )->assertOk();

    $song->refresh();
    expect($song->youtube_video_url)->toBe('https://www.youtube.com/watch?v=existing');
    expect($song->ultimate_guitar_url)->toBe('https://www.ultimate-guitar.com/search.php?q=existing');
});
