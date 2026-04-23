<?php

declare(strict_types=1);

use App\Enums\Genre;
use App\Services\GeminiQuotaExceededException;
use App\Services\GeminiSongService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('uses gemini generateContent endpoint with expected payload', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    config()->set('services.gemini.model', 'gemini-2.5-flash-lite');
    config()->set('services.gemini.timeout_seconds', 5);

    Http::fake([
        '*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [
                        ['text' => '{"energy_level":"high","genre":"Rock","duration_in_seconds":240}'],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $result = $callGemini->invoke(
        $service,
        'Test prompt',
        base64_encode('fake-image-bytes')
    );

    expect($result)->toBe([
        'energy_level' => 'high',
        'genre' => 'Rock',
        'duration_in_seconds' => 240,
    ]);

    Http::assertSent(function (Request $request) {
        expect($request->url())->toContain('/v1beta/models/');
        expect($request->url())->toContain('key=test-gemini-key');

        $payload = $request->data();
        // Should have text and inline_data parts
        expect(count($payload['contents'][0]['parts']))->toBe(2);
        expect($payload['contents'][0]['parts'][1]['inline_data']['mime_type'])->toBe('image/png');

        return true;
    });
});

it('reports as disabled when gemini api key is missing', function () {
    config()->set('services.gemini.api_key', '');

    $service = new GeminiSongService;
    expect($service->isEnabled())->toBeFalse();
});

it('reports as enabled when api key is set', function () {
    config()->set('services.ai.provider', 'gemini');
    config()->set('services.gemini.api_key', 'test-key');

    $service = new GeminiSongService;
    expect($service->isEnabled())->toBeTrue();
});

it('returns provider name as gemini', function () {
    $service = new GeminiSongService;
    expect($service->provider())->toBe('gemini');
});

it('returns null from enrichMetadataFromTitleAndArtist when disabled', function () {
    config()->set('services.gemini.api_key', '');

    $service = new GeminiSongService;
    $result = $service->enrichMetadataFromTitleAndArtist('Test', 'Artist');

    expect($result)->toBeNull();
});

it('enriches metadata from title and artist when enabled', function () {
    config()->set('services.ai.provider', 'gemini');
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [
                        ['text' => '{"energy_level":"medium","genre":"Pop","era":"2010s"}'],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $service = new GeminiSongService;
    $result = $service->enrichMetadataFromTitleAndArtist('Shape of You', 'Ed Sheeran');

    expect($result)->toBeArray();
    expect($result['energy_level'])->toBe('medium');
    expect($result['genre'])->toBe('Pop');
});

it('throws quota exception on 429 response', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response(['error' => ['message' => 'rate limited']], 429),
    ]);

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $callGemini->invoke($service, 'Test prompt');
})->throws(GeminiQuotaExceededException::class);

it('throws quota exception on RESOURCE_EXHAUSTED status', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response([
            'error' => [
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'quota exhausted',
            ],
        ], 403),
    ]);

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $callGemini->invoke($service, 'Test prompt');
})->throws(GeminiQuotaExceededException::class);

it('parses retry delay from error payload', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response([
            'error' => [
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'Please retry in 30s',
                'details' => [
                    [],
                    ['violations' => [['quotaId' => 'GenerateContentRequestsPerMinuteQuota']]],
                    ['retryDelay' => '45s'],
                ],
            ],
        ], 429),
    ]);

    $service = new GeminiSongService;

    try {
        $callGemini = (new ReflectionClass($service))->getMethod('callGemini');
        $callGemini->invoke($service, 'Test prompt');
    } catch (GeminiQuotaExceededException $e) {
        expect($e->retryAfterSeconds())->toBe(45);
    }
});

it('parses retry seconds from error message', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response([
            'error' => [
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'Please retry in 25s',
            ],
        ], 429),
    ]);

    $service = new GeminiSongService;

    try {
        $callGemini = (new ReflectionClass($service))->getMethod('callGemini');
        $callGemini->invoke($service, 'Test prompt');
    } catch (GeminiQuotaExceededException $e) {
        expect($e->retryAfterSeconds())->toBe(25);
    }
});

it('uses PerDay quota window for daily quota limits', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response([
            'error' => [
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'daily quota exceeded',
                'details' => [
                    [],
                    ['violations' => [['quotaId' => 'GenerateContentRequestsPerDay']]],
                ],
            ],
        ], 429),
    ]);

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))->getMethod('callGemini');

    try {
        $callGemini->invoke($service, 'Test prompt');
        test()->fail('Expected GeminiQuotaExceededException was not thrown');
    } catch (GeminiQuotaExceededException $e) {
        // Should be until next day UTC
        expect($e->retryAfterSeconds())->toBeGreaterThan(60);
    }
});

it('throws from quota block cache when model is blocked', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    $model = 'gemini-2.5-flash-lite';
    $key = 'gemini:quota_blocked_until:'.md5($model);
    Cache::put($key, time() + 300, now()->addMinutes(5));

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $callGemini->invoke($service, 'Test prompt');
})->throws(GeminiQuotaExceededException::class, 'Gemini quota cooldown is active');

it('clears quota block after successful response', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    config()->set('services.gemini.model', 'gemini-2.5-flash-lite');

    $model = 'gemini-2.5-flash-lite';
    $key = 'gemini:quota_blocked_until:'.md5($model);
    Cache::put($key, time() - 1, now()->addMinutes(5)); // Expired block

    Http::fake([
        '*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [
                        ['text' => '{"energy_level":"low"}'],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $result = $callGemini->invoke($service, 'Test prompt');
    expect($result)->toBe(['energy_level' => 'low']);

    expect(Cache::has($key))->toBeFalse();
});

it('returns null for non-quota errors', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response(['error' => 'bad request'], 400),
    ]);

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $result = $callGemini->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('handles exception in callGemini by returning null', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => fn () => throw new RuntimeException('Connection timed out'),
    ]);

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $result = $callGemini->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('returns null when gemini response has no text parts', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [
                        ['functionCall' => ['name' => 'test']],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $result = $callGemini->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('returns null when candidates parts is not an array', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response([
            'candidates' => [[
                'content' => 'not-an-object',
            ]],
        ], 200),
    ]);

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $result = $callGemini->invoke($service, 'Test prompt');
    expect($result)->toBeNull();
});

it('strips markdown code fences from gemini response', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [
                        ['text' => "```json\n{\"energy_level\":\"low\"}\n```"],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $result = $callGemini->invoke($service, 'Test prompt');
    expect($result)->toBe(['energy_level' => 'low']);
});

it('sends text-only request when no image is provided', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [
                        ['text' => '{"energy_level":"low"}'],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $service = new GeminiSongService;

    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $result = $callGemini->invoke($service, 'Test prompt', null);
    expect($result)->toBe(['energy_level' => 'low']);

    Http::assertSent(function (Request $request) {
        $payload = $request->data();

        return count($payload['contents'][0]['parts']) === 1;
    });
});

it('normalizes gemini theme values to canonical enum values', function () {
    $service = new GeminiSongService;

    $sanitizeMetadata = (new ReflectionClass($service))
        ->getMethod('sanitizeMetadata');

    expect($sanitizeMetadata->invoke($service, ['theme' => 'Love']))->toMatchArray([
        'theme' => 'love',
    ]);
    expect($sanitizeMetadata->invoke($service, ['theme' => "St Patrick's"]))->toMatchArray([
        'theme' => 'st_patricks',
    ]);
    expect($sanitizeMetadata->invoke($service, ['theme' => 'unknown']))->not->toHaveKey('theme');
});

it('normalizes musical key aliases for gemini', function () {
    $service = new GeminiSongService;

    $normalizeMusicalKey = (new ReflectionClass($service))
        ->getMethod('normalizeMusicalKey');

    expect($normalizeMusicalKey->invoke($service, 'C♯'))->not->toBeNull();
    expect($normalizeMusicalKey->invoke($service, 'B♭'))->not->toBeNull();
    expect($normalizeMusicalKey->invoke($service, 'A#/Bb'))->toBe('Bb');
    expect($normalizeMusicalKey->invoke($service, null))->toBeNull();
    expect($normalizeMusicalKey->invoke($service, 'X#'))->toBeNull();
});

it('normalizes verbose major/minor key suffixes for gemini', function () {
    $service = new GeminiSongService;

    $normalizeMusicalKey = (new ReflectionClass($service))
        ->getMethod('normalizeMusicalKey');

    expect($normalizeMusicalKey->invoke($service, 'G major'))->toBe('G');
    expect($normalizeMusicalKey->invoke($service, 'A minor'))->toBe('Am');
    expect($normalizeMusicalKey->invoke($service, 'F# minor'))->toBe('F#m');
    expect($normalizeMusicalKey->invoke($service, 'Eb major'))->toBe('Eb');
    expect($normalizeMusicalKey->invoke($service, 'Bb major'))->toBe('Bb');
    expect($normalizeMusicalKey->invoke($service, 'C# minor'))->toBe('C#m');
});

it('normalizes genre values for gemini', function () {
    expect(Genre::normalize('punk rock'))->toBe('Rock');
    expect(Genre::normalize('R&B'))->toBe('R&B');
    expect(Genre::normalize('world music'))->toBeNull();
});

it('parses retry seconds from response body', function () {
    $service = new GeminiSongService;

    $resolveRetryAfterSeconds = (new ReflectionClass($service))
        ->getMethod('resolveRetryAfterSeconds');

    // Plain message in body
    $result = $resolveRetryAfterSeconds->invoke(
        $service,
        'Please retry in 42s',
        null
    );
    expect($result)->toBe(42);

    // No match — default to 60
    $result = $resolveRetryAfterSeconds->invoke(
        $service,
        'something else',
        null
    );
    expect($result)->toBe(60);
});

it('returns default retry when no match found', function () {
    $service = new GeminiSongService;

    $resolveRetryAfterSeconds = (new ReflectionClass($service))
        ->getMethod('resolveRetryAfterSeconds');

    $result = $resolveRetryAfterSeconds->invoke($service, '', []);
    expect($result)->toBe(60);
});

it('parses duration with seconds suffix', function () {
    $service = new GeminiSongService;

    $parseDurationToSeconds = (new ReflectionClass($service))
        ->getMethod('parseDurationToSeconds');

    expect($parseDurationToSeconds->invoke($service, '30s'))->toBe(30);
    expect($parseDurationToSeconds->invoke($service, ''))->toBeNull();
    expect($parseDurationToSeconds->invoke($service, '30m'))->toBeNull();
});
