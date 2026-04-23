<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Services\ClaudeSongService;
use App\Services\GeminiSongService;
use App\Services\OpenAiSongService;
use App\Services\SongMetadataAiRepository;

it('uses openai provider when configured', function () {
    config()->set('services.ai.provider', 'openai');

    $chart = Chart::factory()->make();

    $claude = mock(ClaudeSongService::class);
    $claude->shouldReceive('identifyAndEnrich')->never();

    $gemini = mock(GeminiSongService::class);
    $gemini->shouldReceive('identifyAndEnrich')->never();

    $openAi = mock(OpenAiSongService::class);
    $openAi->shouldReceive('provider')->once()->andReturn('openai');
    $openAi->shouldReceive('identifyAndEnrich')
        ->once()
        ->withArgs(fn (Chart $arg): bool => $arg->is($chart))
        ->andReturn(['title' => 'Wonderwall', 'artist' => 'Oasis']);

    $repository = new SongMetadataAiRepository($claude, $gemini, $openAi);

    expect($repository->provider())->toBe('openai')
        ->and($repository->identifyAndEnrich($chart))->toMatchArray([
            'title' => 'Wonderwall',
            'artist' => 'Oasis',
        ]);
});

it('uses anthropic provider when configured', function () {
    config()->set('services.ai.provider', 'anthropic');

    $claude = mock(ClaudeSongService::class);
    $claude->shouldReceive('provider')->once()->andReturn('anthropic');
    $claude->shouldReceive('enrichMetadataFromTitleAndArtist')
        ->once()
        ->with('Fast Car', 'Tracy Chapman')
        ->andReturn(['genre' => 'Folk']);

    $gemini = mock(GeminiSongService::class);
    $gemini->shouldReceive('enrichMetadataFromTitleAndArtist')->never();

    $openAi = mock(OpenAiSongService::class);
    $openAi->shouldReceive('enrichMetadataFromTitleAndArtist')->never();

    $repository = new SongMetadataAiRepository($claude, $gemini, $openAi);

    expect($repository->provider())->toBe('anthropic')
        ->and($repository->enrichMetadataFromTitleAndArtist('Fast Car', 'Tracy Chapman'))
        ->toMatchArray(['genre' => 'Folk']);
});

it('uses gemini provider when configured', function () {
    config()->set('services.ai.provider', 'gemini');

    $claude = mock(ClaudeSongService::class);
    $claude->shouldReceive('enrichMetadataFromTitleAndArtist')->never();

    $gemini = mock(GeminiSongService::class);
    $gemini->shouldReceive('provider')->once()->andReturn('gemini');
    $gemini->shouldReceive('enrichMetadataFromTitleAndArtist')
        ->once()
        ->with('Imagine', 'John Lennon')
        ->andReturn(['genre' => 'Rock']);

    $openAi = mock(OpenAiSongService::class);
    $openAi->shouldReceive('enrichMetadataFromTitleAndArtist')->never();

    $repository = new SongMetadataAiRepository($claude, $gemini, $openAi);

    expect($repository->provider())->toBe('gemini')
        ->and($repository->enrichMetadataFromTitleAndArtist('Imagine', 'John Lennon'))
        ->toMatchArray(['genre' => 'Rock']);
});

it('falls back to anthropic for unknown provider config values', function () {
    config()->set('services.ai.provider', 'unexpected-provider');

    $claude = mock(ClaudeSongService::class);
    $claude->shouldReceive('isEnabled')->once()->andReturnTrue();

    $gemini = mock(GeminiSongService::class);
    $gemini->shouldReceive('isEnabled')->never();

    $openAi = mock(OpenAiSongService::class);
    $openAi->shouldReceive('isEnabled')->never();

    $repository = new SongMetadataAiRepository($claude, $gemini, $openAi);

    expect($repository->isEnabled())->toBeTrue();
});
