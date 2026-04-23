<?php

declare(strict_types=1);

use App\Enums\Genre;
use App\Services\AiQuotaExceededException;
use App\Services\OpenAiSongService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('returns null from enrichMetadataFromTitleAndArtist when disabled', function () {
    config()->set('services.openai.api_key', '');

    $service = new OpenAiSongService;
    $result = $service->enrichMetadataFromTitleAndArtist('Test', 'Artist');

    expect($result)->toBeNull();
});

it('enriches metadata from title and artist when enabled', function () {
    config()->set('services.ai.provider', 'openai');
    config()->set('services.openai.api_key', 'test-openai-key');

    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => '{"energy_level":"medium","genre":"Pop","era":"2010s"}',
                ],
            ]],
        ], 200),
    ]);

    $service = new OpenAiSongService;
    $result = $service->enrichMetadataFromTitleAndArtist('Shape of You', 'Ed Sheeran');

    expect($result)->toBeArray();
    expect($result['energy_level'])->toBe('medium');
    expect($result['genre'])->toBe('Pop');
});

it('throws quota exception on 429 with insufficient_quota error code', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    Http::fake([
        '*' => Http::response([
            'error' => [
                'code' => 'insufficient_quota',
                'message' => 'You exceeded your quota.',
            ],
        ], 429),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $callOpenAi->invoke($service, 'Test prompt');
})->throws(AiQuotaExceededException::class, 'OpenAI API quota exhausted');

it('throws quota exception on 429 with insufficient_quota error type', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    Http::fake([
        '*' => Http::response([
            'error' => [
                'type' => 'insufficient_quota',
                'message' => 'You exceeded your quota.',
            ],
        ], 429),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $callOpenAi->invoke($service, 'Test prompt');
})->throws(AiQuotaExceededException::class, 'OpenAI API quota exhausted');

it('throws standard rate limit exception on 429 without insufficient_quota', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    Http::fake([
        '*' => Http::response([
            'error' => [
                'code' => 'rate_limit_exceeded',
                'message' => 'Rate limit reached.',
            ],
        ], 429),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $callOpenAi->invoke($service, 'Test prompt');
})->throws(AiQuotaExceededException::class, 'OpenAI API rate limit reached');

it('returns null for non-429 non-successful responses', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    Http::fake([
        '*' => Http::response(['error' => 'server error'], 500),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $result = $callOpenAi->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('returns null when openai response has no text content', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => '',
                ],
            ]],
        ], 200),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $result = $callOpenAi->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('handles array content format from openai response', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => [
                        ['text' => '{"energy_level":"high"}'],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $result = $callOpenAi->invoke($service, 'Test prompt');
    expect($result)->toBe(['energy_level' => 'high']);
});

it('returns null when content is non-string non-array', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => 42,
                ],
            ]],
        ], 200),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $result = $callOpenAi->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('returns null when array content has no text parts', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => [
                        ['image' => 'base64data'],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $result = $callOpenAi->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('handles exception in callOpenAi by returning null', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    Http::fake([
        '*' => fn () => throw new RuntimeException('Connection timed out'),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $result = $callOpenAi->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('sends text-only request when no image is provided', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => '{"energy_level":"low"}',
                ],
            ]],
        ], 200),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $result = $callOpenAi->invoke($service, 'Test prompt', null);
    expect($result)->toBe(['energy_level' => 'low']);

    Http::assertSent(function (Request $request) {
        $payload = $request->data();

        return count($payload['messages'][0]['content']) === 1;
    });
});

it('normalizes musical key aliases', function () {
    $service = new OpenAiSongService;

    $normalizeMusicalKey = (new ReflectionClass($service))
        ->getMethod('normalizeMusicalKey');

    expect($normalizeMusicalKey->invoke($service, 'C♯'))->not->toBeNull();
    expect($normalizeMusicalKey->invoke($service, 'B♭'))->not->toBeNull();
    expect($normalizeMusicalKey->invoke($service, 'A#/Bb'))->toBe('Bb');
    expect($normalizeMusicalKey->invoke($service, null))->toBeNull();
});

it('normalizes genre values to broad categories', function () {
    expect(Genre::normalize('punk rock'))->toBe('Rock');
    expect(Genre::normalize('R&B'))->toBe('R&B');
    expect(Genre::normalize('world music'))->toBeNull();
});

it('returns provider name as openai', function () {
    $service = new OpenAiSongService;
    expect($service->provider())->toBe('openai');
});

it('handles non-array payload in extractOpenAiText', function () {
    $service = new OpenAiSongService;

    $extractOpenAiText = (new ReflectionClass($service))
        ->getMethod('extractOpenAiText');

    $result = $extractOpenAiText->invoke($service, 'not an array');
    expect($result)->toBeNull();
});

it('extracts json from text with surrounding content using brace fallback', function () {
    $service = new OpenAiSongService;

    $decodeJsonPayload = (new ReflectionClass($service))
        ->getMethod('decodeJsonPayload');

    $result = $decodeJsonPayload->invoke(
        $service,
        'Result: {"title":"Test","artist":"Band"} done'
    );

    expect($result)->toBe(['title' => 'Test', 'artist' => 'Band']);
});

it('returns null for text with no valid json', function () {
    $service = new OpenAiSongService;

    $decodeJsonPayload = (new ReflectionClass($service))
        ->getMethod('decodeJsonPayload');

    $result = $decodeJsonPayload->invoke($service, 'no json here');
    expect($result)->toBeNull();
});
