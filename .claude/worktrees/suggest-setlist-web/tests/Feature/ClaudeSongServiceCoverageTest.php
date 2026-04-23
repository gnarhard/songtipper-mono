<?php

declare(strict_types=1);

use App\Enums\Genre;
use App\Services\AiQuotaExceededException;
use App\Services\ClaudeSongService;
use Illuminate\Support\Facades\Http;

it('returns null from enrichMetadataFromTitleAndArtist when disabled', function () {
    config()->set('services.anthropic.api_key', '');

    $service = new ClaudeSongService;
    $result = $service->enrichMetadataFromTitleAndArtist('Test', 'Artist');

    expect($result)->toBeNull();
});

it('enriches metadata from title and artist when enabled', function () {
    config()->set('services.ai.provider', 'anthropic');
    config()->set('services.anthropic.api_key', 'test-claude-key');

    config()->set('services.anthropic.model', 'claude-sonnet-4-6');

    // With prefill "{", Claude returns the continuation (without the opening brace)
    Http::fake([
        '*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => '"energy_level":"medium","genre":"Pop","era":"2010s","duration_in_seconds":210}',
            ]],
        ], 200),
    ]);

    $service = new ClaudeSongService;
    $result = $service->enrichMetadataFromTitleAndArtist('Shape of You', 'Ed Sheeran');

    expect($result)->toBeArray();
    expect($result['energy_level'])->toBe('medium');
    expect($result['genre'])->toBe('Pop');
});

it('returns null from enrichMetadataFromTitleAndArtist when API returns null result', function () {
    config()->set('services.anthropic.api_key', 'test-claude-key');

    Http::fake([
        '*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => 'not json',
            ]],
        ], 200),
    ]);

    $service = new ClaudeSongService;

    // callClaude will fail to parse and return null -> decodeJsonPayload fallback
    $result = $service->enrichMetadataFromTitleAndArtist('Test', 'Artist');

    // "not json" has no braces either, so decodeJsonPayload returns null
    expect($result)->toBeNull();
});

it('throws quota exception on 429 rate limit', function () {
    config()->set('services.anthropic.api_key', 'test-claude-key');

    Http::fake([
        '*' => Http::response(['error' => 'rate limited'], 429),
    ]);

    $service = new ClaudeSongService;

    $callClaude = (new ReflectionClass($service))
        ->getMethod('callClaude');

    $callClaude->invoke($service, 'Test prompt');
})->throws(AiQuotaExceededException::class, 'Anthropic API rate limit reached');

it('throws quota exception on 400 with credit balance too low', function () {
    config()->set('services.anthropic.api_key', 'test-claude-key');

    Http::fake([
        '*' => Http::response([
            'error' => [
                'type' => 'invalid_request_error',
                'message' => 'Your credit balance is too low to access the Anthropic API.',
            ],
        ], 400),
    ]);

    $service = new ClaudeSongService;

    $callClaude = (new ReflectionClass($service))
        ->getMethod('callClaude');

    $callClaude->invoke($service, 'Test prompt');
})->throws(AiQuotaExceededException::class, 'Anthropic API credit balance is too low');

it('returns null for non-quota non-successful responses', function () {
    config()->set('services.anthropic.api_key', 'test-claude-key');

    Http::fake([
        '*' => Http::response(['error' => 'server error'], 500),
    ]);

    $service = new ClaudeSongService;

    $callClaude = (new ReflectionClass($service))
        ->getMethod('callClaude');

    $result = $callClaude->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('returns null when claude response has no text content', function () {
    config()->set('services.anthropic.api_key', 'test-claude-key');

    Http::fake([
        '*' => Http::response([
            'content' => [[
                'type' => 'image',
            ]],
        ], 200),
    ]);

    $service = new ClaudeSongService;

    $callClaude = (new ReflectionClass($service))
        ->getMethod('callClaude');

    $result = $callClaude->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('returns null when content is not an array', function () {
    config()->set('services.anthropic.api_key', 'test-claude-key');

    Http::fake([
        '*' => Http::response([
            'content' => 'not-an-array',
        ], 200),
    ]);

    $service = new ClaudeSongService;

    $callClaude = (new ReflectionClass($service))
        ->getMethod('callClaude');

    $result = $callClaude->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('returns null when payload is not an array', function () {
    config()->set('services.anthropic.api_key', 'test-claude-key');

    Http::fake([
        '*' => Http::response('plain text', 200),
    ]);

    $service = new ClaudeSongService;

    $callClaude = (new ReflectionClass($service))
        ->getMethod('callClaude');

    $result = $callClaude->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('handles exception in callClaude by returning null', function () {
    config()->set('services.anthropic.api_key', 'test-claude-key');

    // Simulate a connection timeout
    Http::fake([
        '*' => fn () => throw new RuntimeException('Connection timed out'),
    ]);

    $service = new ClaudeSongService;

    $callClaude = (new ReflectionClass($service))
        ->getMethod('callClaude');

    $result = $callClaude->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('sends text-only request when no image is provided', function () {
    config()->set('services.anthropic.api_key', 'test-claude-key');

    Http::fake([
        '*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => '{"energy_level":"low"}',
            ]],
        ], 200),
    ]);

    $service = new ClaudeSongService;

    $callClaude = (new ReflectionClass($service))
        ->getMethod('callClaude');

    $result = $callClaude->invoke($service, 'Test prompt', null);
    expect($result)->toBe(['energy_level' => 'low']);

    Http::assertSent(function ($request) {
        $payload = $request->data();

        // Should only have 1 content item (text), no image
        return count($payload['messages'][0]['content']) === 1;
    });
});

it('extracts json from text with surrounding non-json content', function () {
    $service = new ClaudeSongService;

    $decodeJsonPayload = (new ReflectionClass($service))
        ->getMethod('decodeJsonPayload');

    $result = $decodeJsonPayload->invoke(
        $service,
        'Here is the result: {"title":"Test","artist":"Band"} and some more text'
    );

    expect($result)->toBe(['title' => 'Test', 'artist' => 'Band']);
});

it('returns null for text with no valid json', function () {
    $service = new ClaudeSongService;

    $decodeJsonPayload = (new ReflectionClass($service))
        ->getMethod('decodeJsonPayload');

    $result = $decodeJsonPayload->invoke($service, 'no json here at all');
    expect($result)->toBeNull();
});

it('returns null when braces are in wrong order', function () {
    $service = new ClaudeSongService;

    $decodeJsonPayload = (new ReflectionClass($service))
        ->getMethod('decodeJsonPayload');

    $result = $decodeJsonPayload->invoke($service, '} before {');
    expect($result)->toBeNull();
});

it('normalizes musical key aliases', function () {
    $service = new ClaudeSongService;

    $normalizeMusicalKey = (new ReflectionClass($service))
        ->getMethod('normalizeMusicalKey');

    // Test sharp symbol normalization
    expect($normalizeMusicalKey->invoke($service, 'C♯'))->not->toBeNull();

    // Test flat symbol normalization
    expect($normalizeMusicalKey->invoke($service, 'B♭'))->not->toBeNull();

    // Test alias resolution
    expect($normalizeMusicalKey->invoke($service, 'A#/Bb'))->toBe('Bb');

    // Test null input
    expect($normalizeMusicalKey->invoke($service, null))->toBeNull();

    // Test unknown key
    expect($normalizeMusicalKey->invoke($service, 'X#'))->toBeNull();
});

it('normalizes genre values to broad categories', function () {
    expect(Genre::normalize('punk rock'))->toBe('Rock');
    expect(Genre::normalize('hip-hop'))->toBe('Hip Hop');
    expect(Genre::normalize('R&B'))->toBe('R&B');
    expect(Genre::normalize('synthpop'))->toBe('Pop');
    expect(Genre::normalize('bluegrass'))->toBe('Country');
    expect(Genre::normalize('swing'))->toBe('Jazz');
    expect(Genre::normalize('blues'))->toBe('Blues');
    expect(Genre::normalize('techno'))->toBe('Electronic');
    expect(Genre::normalize('acoustic'))->toBe('Folk');
    expect(Genre::normalize('reggaeton'))->toBe('Latin');
    expect(Genre::normalize('ska'))->toBe('Reggae');
    expect(Genre::normalize('orchestral'))->toBe('Classical');
    expect(Genre::normalize('world music'))->toBeNull();
});

it('sanitizes metadata with mood fallback for theme', function () {
    $service = new ClaudeSongService;

    $sanitizeMetadata = (new ReflectionClass($service))
        ->getMethod('sanitizeMetadata');

    $result = $sanitizeMetadata->invoke($service, ['mood' => 'love']);
    expect($result)->toHaveKey('theme', 'love');
});

it('sanitizes metadata with duration boundaries', function () {
    $service = new ClaudeSongService;

    $sanitizeMetadata = (new ReflectionClass($service))
        ->getMethod('sanitizeMetadata');

    // Valid duration
    $result = $sanitizeMetadata->invoke($service, ['duration_in_seconds' => 300]);
    expect($result)->toHaveKey('duration_in_seconds', 300);

    // Over 86400 should be excluded
    $result = $sanitizeMetadata->invoke($service, ['duration_in_seconds' => 100000]);
    expect($result)->not->toHaveKey('duration_in_seconds');

    // Negative should be excluded
    $result = $sanitizeMetadata->invoke($service, ['duration_in_seconds' => -1]);
    expect($result)->not->toHaveKey('duration_in_seconds');
});

it('returns provider name as anthropic', function () {
    $service = new ClaudeSongService;
    expect($service->provider())->toBe('anthropic');
});

it('handles numeric string as integer', function () {
    $service = new ClaudeSongService;

    $asInteger = (new ReflectionClass($service))
        ->getMethod('asInteger');

    expect($asInteger->invoke($service, '42'))->toBe(42);
    expect($asInteger->invoke($service, '3.14'))->toBeNull();
    expect($asInteger->invoke($service, 'abc'))->toBeNull();
    expect($asInteger->invoke($service, null))->toBeNull();
});
