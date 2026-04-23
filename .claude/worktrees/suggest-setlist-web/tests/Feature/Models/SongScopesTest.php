<?php

declare(strict_types=1);

use App\Enums\EnergyLevel;
use App\Models\Chart;
use App\Models\Request;
use App\Models\Song;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('has many project songs', function () {
    $model = new Song;
    $relation = $model->projectSongs();

    expect($relation)->toBeInstanceOf(HasMany::class);
});

it('has many charts', function () {
    $model = new Song;
    $relation = $model->charts();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Chart::class);
});

it('has many requests', function () {
    $model = new Song;
    $relation = $model->requests();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Request::class);
});

it('determines hasCompleteMetadata correctly', function () {
    $song = new Song;
    $song->energy_level = EnergyLevel::Medium;
    $song->era = '90s';
    $song->genre = 'Rock';
    $song->original_musical_key = null;
    $song->duration_in_seconds = 240;

    expect($song->hasCompleteMetadata())->toBeFalse();
});

it('reports complete metadata when all fields are set', function () {
    $song = Song::factory()->create([
        'energy_level' => EnergyLevel::Medium,
        'era' => '90s',
        'genre' => 'Rock',
        'original_musical_key' => 'C',
        'duration_in_seconds' => 240,
    ]);

    expect($song->hasCompleteMetadata())->toBeTrue();
});

it('scopes by search term', function () {
    Song::factory()->create(['title' => 'Bohemian Rhapsody', 'artist' => 'Queen']);
    Song::factory()->create(['title' => 'Stairway to Heaven', 'artist' => 'Led Zeppelin']);

    $results = Song::query()->search('Bohemian')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->title)->toBe('Bohemian Rhapsody');
});

it('scopes by energy level', function () {
    Song::factory()->create(['energy_level' => EnergyLevel::Low]);
    Song::factory()->create(['energy_level' => EnergyLevel::High]);

    $results = Song::query()->energyLevel(EnergyLevel::Low->value)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->energy_level)->toBe(EnergyLevel::Low);
});

it('scopes by era', function () {
    Song::factory()->create(['era' => '90s']);
    Song::factory()->create(['era' => '80s']);

    $results = Song::query()->era('90s')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->era)->toBe('90s');
});

it('scopes by genre', function () {
    Song::factory()->create(['genre' => 'Rock']);
    Song::factory()->create(['genre' => 'Jazz']);

    $results = Song::query()->genre('Rock')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->genre)->toBe('Rock');
});

it('scopes by theme', function () {
    Song::factory()->create(['theme' => 'love']);
    Song::factory()->create(['theme' => 'party']);

    $results = Song::query()->theme('love')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->theme)->toBe('love');
});

it('normalizes era value with non-decade number', function () {
    $song = Song::factory()->create(['era' => '1995s']);

    expect($song->fresh()->era)->toBe('1995s');
});

it('normalizes era short format', function () {
    $song = Song::factory()->create(['era' => '80s']);

    expect($song->fresh()->era)->toBe('80s');
});

it('normalizes empty era to null', function () {
    $song = Song::factory()->create(['era' => '']);

    expect($song->fresh()->era)->toBeNull();
});

it('normalizes whitespace-only era to null', function () {
    $song = Song::factory()->create(['era' => '   ']);

    expect($song->fresh()->era)->toBeNull();
});

it('normalizes null era to null', function () {
    $song = Song::factory()->create(['era' => null]);

    expect($song->fresh()->era)->toBeNull();
});

it('creates original request song', function () {
    $song = Song::originalRequestSong();

    expect($song->title)->toBe(Song::ORIGINAL_REQUEST_TITLE);
    expect($song->artist)->toBe(Song::ORIGINAL_REQUEST_ARTIST);
});

it('identifies original request song', function () {
    $song = Song::originalRequestSong();
    expect(Song::isOriginalRequestSong($song))->toBeTrue();

    $otherSong = Song::factory()->create();
    expect(Song::isOriginalRequestSong($otherSong))->toBeFalse();
});

it('creates tip jar support song', function () {
    $song = Song::tipJarSupportSong();

    expect($song->title)->toBe(Song::TIP_JAR_SUPPORT_TITLE);
    expect($song->artist)->toBe(Song::TIP_JAR_SUPPORT_ARTIST);
});

it('identifies tip jar support song', function () {
    $song = Song::tipJarSupportSong();
    expect(Song::isTipJarSupportSong($song))->toBeTrue();

    $otherSong = Song::factory()->create();
    expect(Song::isTipJarSupportSong($otherSong))->toBeFalse();
});

it('sets normalized key on creation when empty', function () {
    $song = Song::factory()->create([
        'title' => 'Test Song',
        'artist' => 'Test Artist',
        'normalized_key' => '',
    ]);

    expect($song->normalized_key)->toBe(Song::generateNormalizedKey('Test Song', 'Test Artist'));
});
