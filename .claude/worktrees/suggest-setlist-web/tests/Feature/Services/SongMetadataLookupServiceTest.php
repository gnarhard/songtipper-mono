<?php

declare(strict_types=1);

use App\Models\Song;
use App\Services\SongMetadataAiProvider;
use App\Services\SongMetadataLookupService;

it('returns song metadata from songs table when AI returns empty and song exists', function () {
    $song = Song::factory()->create([
        'title' => 'Test Song',
        'artist' => 'Test Artist',
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
        ->andReturn(null);
    $aiProvider->shouldReceive('provider')
        ->once()
        ->andReturn('gemini');

    $service = new SongMetadataLookupService($aiProvider);

    $result = $service->lookup($song->title, $song->artist);

    expect($result['source'])->toBe('none')
        ->and($result['provider_called'])->toBeTrue()
        ->and($result['provider_name'])->toBe('gemini')
        ->and($result['metadata'])->toBeArray();
});
