<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Services\SongDataIntegrityService;

it('returns no issues for clean data', function () {
    Song::factory()->create(['title' => 'Bohemian Rhapsody', 'artist' => 'Queen']);

    $service = app(SongDataIntegrityService::class);
    $results = $service->runAllChecks();

    // Most checks should find nothing for well-formed data
    expect($results)->not->toHaveKey('duplicate_normalized_keys')
        ->and($results)->not->toHaveKey('extra_whitespace')
        ->and($results)->not->toHaveKey('placeholder_values')
        ->and($results)->not->toHaveKey('very_short_values')
        ->and($results)->not->toHaveKey('suspicious_characters');
});

it('detects extra whitespace in title', function () {
    Song::unguard();
    Song::query()->insert([
        'title' => '  Stairway to Heaven  ',
        'artist' => 'Led Zeppelin',
        'normalized_key' => 'stairwaytoheaven|ledzeppelin',
    ]);
    Song::reguard();

    $service = app(SongDataIntegrityService::class);
    $songs = $service->findExtraWhitespace();

    expect($songs)->toHaveCount(1);
});

it('detects double spaces in artist', function () {
    Song::unguard();
    Song::query()->insert([
        'title' => 'Yesterday',
        'artist' => 'The  Beatles',
        'normalized_key' => 'yesterday|thebeatles',
    ]);
    Song::reguard();

    $service = app(SongDataIntegrityService::class);
    $songs = $service->findExtraWhitespace();

    expect($songs)->toHaveCount(1);
});

it('detects placeholder values', function () {
    Song::factory()->create(['title' => 'test', 'artist' => 'test artist']);

    $service = app(SongDataIntegrityService::class);
    $songs = $service->findPlaceholderValues();

    expect($songs)->toHaveCount(1);
});

it('detects very short values', function () {
    Song::unguard();
    Song::query()->insert([
        'title' => 'X',
        'artist' => 'A',
        'normalized_key' => 'x|a',
    ]);
    Song::reguard();

    $service = app(SongDataIntegrityService::class);
    $songs = $service->findVeryShortValues();

    expect($songs)->toHaveCount(1);
});

it('does not flag system songs', function () {
    Song::findOrCreateByTitleAndArtist(Song::ORIGINAL_REQUEST_TITLE, Song::ORIGINAL_REQUEST_ARTIST);
    Song::findOrCreateByTitleAndArtist(Song::TIP_JAR_SUPPORT_TITLE, Song::TIP_JAR_SUPPORT_ARTIST);

    $service = app(SongDataIntegrityService::class);
    $results = $service->runAllChecks();

    // System songs should never appear in any check results
    $allFlaggedSongs = collect($results)->flatMap(fn ($r) => $r['songs']);
    $systemSongs = $allFlaggedSongs->filter(
        fn (Song $song) => Song::isOriginalRequestSong($song) || Song::isTipJarSupportSong($song)
    );
    expect($systemSongs)->toBeEmpty();
});

it('finds near duplicates with similar artist names', function () {
    Song::factory()->create(['title' => 'Hey Jude', 'artist' => 'The Beatles']);
    Song::factory()->create(['title' => 'Hey Jude', 'artist' => 'Beatles']);

    $service = app(SongDataIntegrityService::class);
    $songs = $service->findNearDuplicates();

    expect($songs)->toHaveCount(2);
});

it('finds near duplicates with similar titles', function () {
    Song::factory()->create(['title' => 'Don\'t Stop Believin\'', 'artist' => 'Journey']);
    Song::factory()->create(['title' => 'Dont Stop Believing', 'artist' => 'Journey']);

    $service = app(SongDataIntegrityService::class);
    $songs = $service->findNearDuplicates();

    expect($songs)->toHaveCount(2);
});

it('does not flag dissimilar songs as near duplicates', function () {
    Song::factory()->create(['title' => 'Bohemian Rhapsody', 'artist' => 'Queen']);
    Song::factory()->create(['title' => 'Stairway to Heaven', 'artist' => 'Led Zeppelin']);

    $service = app(SongDataIntegrityService::class);
    $songs = $service->findNearDuplicates();

    expect($songs)->toBeEmpty();
});

it('finds orphaned songs', function () {
    $orphan = Song::factory()->create(['title' => 'Lost Song', 'artist' => 'No One']);

    $linked = Song::factory()->create(['title' => 'Linked Song', 'artist' => 'Someone']);
    $project = Project::factory()->create();
    ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $linked->id,
    ]);

    $service = app(SongDataIntegrityService::class);
    $songs = $service->findOrphanedSongs();

    expect($songs->pluck('id')->all())->toContain($orphan->id)
        ->and($songs->pluck('id')->all())->not->toContain($linked->id);
});

it('fixes whitespace issues', function () {
    Song::unguard();
    $song = new Song;
    $song->title = '  Stairway to Heaven  ';
    $song->artist = 'Led  Zeppelin';
    $song->normalized_key = 'stairwaytoheaven|ledzeppelin';
    $song->save();
    Song::reguard();

    $service = app(SongDataIntegrityService::class);
    $fixed = $service->fixWhitespace($song);

    expect($fixed)->toBeTrue()
        ->and($song->fresh()->title)->toBe('Stairway to Heaven')
        ->and($song->fresh()->artist)->toBe('Led Zeppelin');
});

it('fixes casing issues', function () {
    $song = Song::factory()->create(['title' => 'bohemian rhapsody', 'artist' => 'QUEEN']);

    $service = app(SongDataIntegrityService::class);
    $fixed = $service->fixCasing($song);

    expect($fixed)->toBeTrue()
        ->and($song->fresh()->title)->toBe('Bohemian Rhapsody')
        ->and($song->fresh()->artist)->toBe('Queen');
});

it('title-cases while keeping articles lowercase', function () {
    $song = Song::factory()->create(['title' => 'BRIDGE OVER THE RIVER', 'artist' => 'Some Artist']);

    $service = app(SongDataIntegrityService::class);
    $service->fixCasing($song);

    expect($song->fresh()->title)->toBe('Bridge Over the River');
});

it('merges a duplicate into the canonical song', function () {
    $canonical = Song::factory()->create(['title' => 'Hey Jude', 'artist' => 'The Beatles']);
    $duplicate = Song::factory()->create(['title' => 'Hey Jude', 'artist' => 'Beatles']);

    $project = Project::factory()->create();
    ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $duplicate->id,
    ]);

    $service = app(SongDataIntegrityService::class);
    $service->mergeDuplicate($canonical, $duplicate);

    expect(ProjectSong::where('song_id', $canonical->id)->count())->toBe(1)
        ->and(Song::find($duplicate->id))->toBeNull();
});

it('lists all available checks', function () {
    $service = app(SongDataIntegrityService::class);
    $checks = $service->checks();

    expect($checks)->toHaveKeys([
        'duplicate_normalized_keys',
        'near_duplicates',
        'title_casing',
        'artist_casing',
        'extra_whitespace',
        'suspicious_characters',
        'placeholder_values',
        'very_short_values',
        'title_contains_artist_prefix',
        'orphaned_songs',
    ]);
});
