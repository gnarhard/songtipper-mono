<?php

declare(strict_types=1);

use App\Services\YoutubeVideoResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config()->set('services.youtube.api_key', 'test-api-key');
    config()->set('services.youtube.timeout_seconds', 1);
});

it('returns null when api key is empty', function () {
    config()->set('services.youtube.api_key', '');

    $resolver = new YoutubeVideoResolver;
    $result = $resolver->resolveMostRelevantVideoUrl('Song', 'Artist');

    expect($result)->toBeNull();
});

it('returns null when api key is not configured', function () {
    config()->set('services.youtube.api_key', null);

    $resolver = new YoutubeVideoResolver;
    $result = $resolver->resolveMostRelevantVideoUrl('Song', 'Artist');

    expect($result)->toBeNull();
});

it('returns null when response is not successful', function () {
    Http::fake([
        'googleapis.com/*' => Http::response(null, 403),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $msg) => str_contains($msg, 'YouTube search request failed'));

    $resolver = new YoutubeVideoResolver;
    $result = $resolver->resolveMostRelevantVideoUrl('Test Song', 'Test Artist');

    expect($result)->toBeNull();
});

it('returns null when an exception is thrown', function () {
    Http::fake([
        'googleapis.com/*' => Http::response(fn () => throw new RuntimeException('Connection error')),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $msg) => str_contains($msg, 'YouTube search request threw an exception'));

    $resolver = new YoutubeVideoResolver;
    $result = $resolver->resolveMostRelevantVideoUrl('Test Song', 'Test Artist');

    expect($result)->toBeNull();
});

it('builds query with only title when artist is empty', function () {
    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [['id' => ['videoId' => 'abc123']]],
        ]),
    ]);

    $resolver = new YoutubeVideoResolver;
    $result = $resolver->resolveMostRelevantVideoUrl('My Song', '');

    expect($result)->toBe('https://www.youtube.com/watch?v=abc123');

    Http::assertSent(function ($request) {
        return $request['q'] === 'My Song music video';
    });
});

it('builds query with only artist when title is empty', function () {
    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [['id' => ['videoId' => 'def456']]],
        ]),
    ]);

    $resolver = new YoutubeVideoResolver;
    $result = $resolver->resolveMostRelevantVideoUrl('', 'Great Artist');

    expect($result)->toBe('https://www.youtube.com/watch?v=def456');

    Http::assertSent(function ($request) {
        return $request['q'] === 'Great Artist music video';
    });
});

it('builds query with music video fallback when both are empty', function () {
    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [['id' => ['videoId' => 'ghi789']]],
        ]),
    ]);

    $resolver = new YoutubeVideoResolver;
    $result = $resolver->resolveMostRelevantVideoUrl('', '');

    Http::assertSent(function ($request) {
        return $request['q'] === 'music video';
    });
});
