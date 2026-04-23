<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\Song;
use App\Services\GeminiSongService;
use App\Services\GeminiSongServiceTinker;

it('reports gemini runtime status', function () {
    config()->set('services.gemini.api_key', 'test-key');
    config()->set('services.gemini.model', 'gemini-2.5-flash-lite');
    config()->set('services.gemini.timeout_seconds', 30);

    $gemini = mock(GeminiSongService::class);
    $gemini->shouldReceive('isEnabled')->once()->andReturnTrue();

    $helper = new GeminiSongServiceTinker($gemini);

    expect($helper->status())->toBe([
        'enabled' => true,
        'api_key_set' => true,
        'model' => 'gemini-2.5-flash-lite',
        'timeout_seconds' => 30,
    ]);
});

it('identifies a chart by id', function () {
    $chart = Chart::factory()->create();

    $expected = ['title' => 'Wonderwall', 'artist' => 'Oasis'];

    $gemini = mock(GeminiSongService::class);
    $gemini->shouldReceive('identifyAndEnrich')
        ->once()
        ->withArgs(fn (Chart $arg): bool => $arg->is($chart))
        ->andReturn($expected);

    $helper = new GeminiSongServiceTinker($gemini);

    expect($helper->identifyChart($chart->id))->toBe($expected);
});

it('enriches a song using latest chart when chart id is omitted', function () {
    $song = Song::factory()->create([
        'energy_level' => null,
        'era' => null,
        'genre' => null,
        'original_musical_key' => null,
        'duration_in_seconds' => null,
    ]);

    Chart::factory()->create(['song_id' => $song->id]);
    $latestChart = Chart::factory()->create(['song_id' => $song->id]);

    $gemini = mock(GeminiSongService::class);
    $gemini->shouldReceive('enrich')
        ->once()
        ->withArgs(fn (Song $songArg, Chart $chartArg): bool => $songArg->is($song) && $chartArg->is($latestChart));

    $helper = new GeminiSongServiceTinker($gemini);
    $result = $helper->enrichSong($song->id);

    expect($result->id)->toBe($song->id);
});

it('throws when song has no charts and chart id is omitted', function () {
    $song = Song::factory()->create();

    $gemini = mock(GeminiSongService::class);

    $helper = new GeminiSongServiceTinker($gemini);

    expect(fn () => $helper->enrichSong($song->id))
        ->toThrow(InvalidArgumentException::class, 'No chart found for this song. Provide a chart_id or upload a chart first.');
});

it('enriches a song using explicit chart id', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create(['song_id' => $song->id]);

    $gemini = mock(GeminiSongService::class);
    $gemini->shouldReceive('enrich')
        ->once()
        ->withArgs(fn (Song $songArg, Chart $chartArg): bool => $songArg->is($song) && $chartArg->is($chart));

    $helper = new GeminiSongServiceTinker($gemini);
    $result = $helper->enrichSong($song->id, $chart->id);

    expect($result->id)->toBe($song->id);
});

it('lists recent charts in descending id order', function () {
    $first = Chart::factory()->create();
    $second = Chart::factory()->create();

    $gemini = mock(GeminiSongService::class);
    $helper = new GeminiSongServiceTinker($gemini);

    $charts = $helper->recentCharts(2);

    expect($charts)->toHaveCount(2)
        ->and($charts->pluck('chart_id')->all())->toBe([$second->id, $first->id]);
});
