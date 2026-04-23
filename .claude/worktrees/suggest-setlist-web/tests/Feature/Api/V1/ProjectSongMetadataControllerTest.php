<?php

declare(strict_types=1);

use App\Enums\EnergyLevel;
use App\Enums\MusicalKey;
use App\Models\Project;
use App\Models\Song;
use App\Models\User;
use App\Services\AccountUsageService;
use App\Services\SongMetadataAiProvider;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
});

it('returns metadata from songs table without calling gemini', function () {
    $song = Song::factory()->create([
        'title' => 'Wonderwall',
        'artist' => 'Oasis',
        'normalized_key' => Song::generateNormalizedKey('Wonderwall', 'Oasis'),
        'energy_level' => EnergyLevel::High,
        'era' => '90s',
        'genre' => 'Rock',
        'theme' => 'love',
        'original_musical_key' => MusicalKey::FSharp,
        'duration_in_seconds' => 259,
    ]);

    expect($song->energy_level)->toBe(EnergyLevel::High);

    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldReceive('enrichMetadataFromTitleAndArtist')->never();
    app()->instance(SongMetadataAiProvider::class, $aiProvider);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/metadata?title=Wonderwall&artist=Oasis"
    );

    $response->assertOk()
        ->assertJsonPath('data.source', 'songs_table')
        ->assertJsonPath('data.metadata.energy_level', 'high')
        ->assertJsonPath('data.metadata.era', '90s')
        ->assertJsonPath('data.metadata.genre', 'Rock')
        ->assertJsonPath('data.metadata.theme', 'love')
        ->assertJsonPath('data.metadata.original_musical_key', 'F#')
        ->assertJsonPath('data.metadata.performed_musical_key', 'F#')
        ->assertJsonPath('data.metadata.duration_in_seconds', 259);
});

it('prefers project_songs performed key when song already exists in project', function () {
    $song = Song::factory()->create([
        'title' => 'Wonderwall',
        'artist' => 'Oasis',
        'normalized_key' => Song::generateNormalizedKey('Wonderwall', 'Oasis'),
        'energy_level' => EnergyLevel::High,
        'era' => '90s',
        'genre' => 'Rock',
        'theme' => 'party',
        'original_musical_key' => MusicalKey::FSharp,
        'duration_in_seconds' => 259,
    ]);

    $this->project->projectSongs()->create([
        'song_id' => $song->id,
        'performed_musical_key' => MusicalKey::A,
    ]);

    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldReceive('enrichMetadataFromTitleAndArtist')->never();
    app()->instance(SongMetadataAiProvider::class, $aiProvider);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/metadata?title=Wonderwall&artist=Oasis"
    );

    $response->assertOk()
        ->assertJsonPath('data.source', 'songs_table')
        ->assertJsonPath('data.metadata.theme', 'party')
        ->assertJsonPath('data.metadata.original_musical_key', 'F#')
        ->assertJsonPath('data.metadata.performed_musical_key', 'A');
});

it('falls back to configured ai provider when songs table has no metadata', function () {
    Song::factory()->create([
        'title' => 'Imagine',
        'artist' => 'John Lennon',
        'normalized_key' => Song::generateNormalizedKey('Imagine', 'John Lennon'),
        'energy_level' => null,
        'era' => null,
        'genre' => null,
        'theme' => null,
        'original_musical_key' => null,
        'duration_in_seconds' => null,
    ]);

    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldReceive('enrichMetadataFromTitleAndArtist')
        ->once()
        ->with('Imagine', 'John Lennon')
        ->andReturn([
            'energy_level' => 'medium',
            'genre' => 'Rock',
            'duration_in_seconds' => 183,
        ]);
    $aiProvider->shouldReceive('provider')->once()->andReturn('openai');
    app()->instance(SongMetadataAiProvider::class, $aiProvider);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/metadata?title=Imagine&artist=John%20Lennon"
    );

    $response->assertOk()
        ->assertJsonPath('data.source', 'openai')
        ->assertJsonPath('data.metadata.energy_level', 'medium')
        ->assertJsonPath('data.metadata.genre', 'Rock')
        ->assertJsonPath('data.metadata.duration_in_seconds', 183)
        ->assertJsonPath('data.metadata.era', null)
        ->assertJsonPath('data.metadata.original_musical_key', null)
        ->assertJsonPath('data.metadata.performed_musical_key', null);
});

it('returns none source when configured ai provider does not provide metadata', function () {
    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldReceive('enrichMetadataFromTitleAndArtist')
        ->once()
        ->with('Unknown Song', 'Unknown Artist')
        ->andReturnNull();
    app()->instance(SongMetadataAiProvider::class, $aiProvider);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/metadata?title=Unknown%20Song&artist=Unknown%20Artist"
    );

    $response->assertOk()
        ->assertJsonPath('data.source', 'none')
        ->assertJsonPath('data.metadata.energy_level', null)
        ->assertJsonPath('data.metadata.era', null)
        ->assertJsonPath('data.metadata.genre', null)
        ->assertJsonPath('data.metadata.original_musical_key', null)
        ->assertJsonPath('data.metadata.performed_musical_key', null)
        ->assertJsonPath('data.metadata.duration_in_seconds', null);
});

it('returns 404 when user has no access to the project', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/metadata?title=Wonderwall&artist=Oasis"
    );

    $response->assertNotFound();
});

it('returns ai limit response when ai usage is exhausted', function () {
    Sanctum::actingAs($this->owner);

    Song::factory()->create([
        'title' => 'No Metadata Song',
        'artist' => 'Unknown Artist',
        'normalized_key' => Song::generateNormalizedKey('No Metadata Song', 'Unknown Artist'),
        'energy_level' => null,
        'era' => null,
        'genre' => null,
        'theme' => null,
        'original_musical_key' => null,
        'duration_in_seconds' => null,
    ]);

    $this->partialMock(AccountUsageService::class, function (MockInterface $mock) {
        $mock->shouldReceive('aiInteractiveLimitResponse')
            ->once()
            ->andReturn([
                'body' => ['code' => 'ai_limit_reached', 'message' => 'AI usage limit reached.'],
                'status' => 429,
            ]);
        $mock->shouldReceive('recordAiOperation')->never();
    });

    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/metadata?title=No%20Metadata%20Song&artist=Unknown%20Artist"
    );

    $response->assertStatus(429)
        ->assertJsonPath('code', 'ai_limit_reached');
});

it('requires authentication', function () {
    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/metadata?title=Wonderwall&artist=Oasis"
    );

    $response->assertUnauthorized();
});
