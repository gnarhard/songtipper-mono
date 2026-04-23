<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\Song;
use App\Services\GeminiSongService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('uses google search tool for gemini requests', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    config()->set('services.gemini.model', 'gemini-2.5-flash-lite');
    config()->set('services.gemini.timeout_seconds', 5);

    Http::fake([
        '*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [
                        ['text' => '{"energy_level":"high","era":"90s","genre":"Rock","original_musical_key":"C","duration_in_seconds":240}'],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $service = new GeminiSongService;

    $song = Song::factory()->create([
        'title' => 'Wonderwall',
        'artist' => 'Oasis',
        'energy_level' => null,
        'era' => null,
        'genre' => null,
        'original_musical_key' => null,
        'duration_in_seconds' => null,
    ]);

    $chart = Chart::factory()->create(['song_id' => $song->id]);

    // We can't actually call enrich() because it needs a real PDF on disk.
    // Instead, test callGemini via reflection to verify request structure.
    $callGemini = (new ReflectionClass($service))
        ->getMethod('callGemini');

    $result = $callGemini->invoke(
        $service,
        'Test prompt',
        base64_encode('fake-image-bytes')
    );

    expect($result)->toBe([
        'energy_level' => 'high',
        'era' => '90s',
        'genre' => 'Rock',
        'original_musical_key' => 'C',
        'duration_in_seconds' => 240,
    ]);

    Http::assertSent(function (Request $request) {
        $payload = $request->data();

        expect($payload['tools'] ?? [])->toHaveCount(1);
        expect($payload['tools'][0] ?? [])->toHaveKey('google_search');
        expect($payload['tools'][0] ?? [])->not->toHaveKey('google_search_retrieval');

        // response_mime_type must NOT be set — it is incompatible with google_search tool.
        expect($payload['generation_config'] ?? [])->not->toHaveKey('response_mime_type');

        return true;
    });
});

it('does not include capo, tuning, or performed_musical_key in prompts', function () {
    $service = new GeminiSongService;

    $identificationPrompt = (new ReflectionClass($service))
        ->getMethod('buildIdentificationPrompt')
        ->invoke($service);

    expect($identificationPrompt)->not->toContain('capo');
    expect($identificationPrompt)->not->toContain('tuning');
    expect($identificationPrompt)->not->toContain('performed_musical_key');
});

it('reports as disabled when api key is missing', function () {
    config()->set('services.gemini.api_key', '');

    $service = new GeminiSongService;
    expect($service->isEnabled())->toBeFalse();
});

it('strips markdown code fences from gemini response', function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');

    config()->set('services.gemini.model', 'gemini-2.5-flash-lite');

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

    $result = $callGemini->invoke(
        $service,
        'Test prompt',
        base64_encode('fake-image-bytes')
    );

    expect($result)->toBe(['energy_level' => 'low']);
});

it('normalizes detailed genres to broad generic categories', function () {
    $service = new GeminiSongService;

    $sanitizeMetadata = (new ReflectionClass($service))
        ->getMethod('sanitizeMetadata');

    expect($sanitizeMetadata->invoke($service, ['genre' => 'Punk rock']))->toMatchArray(['genre' => 'Rock']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Alternative rock']))->toMatchArray(['genre' => 'Rock']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Pop rock']))->toMatchArray(['genre' => 'Rock']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Synth-pop']))->toMatchArray(['genre' => 'Pop']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Trap']))->toMatchArray(['genre' => 'Hip Hop']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Neo soul']))->toMatchArray(['genre' => 'R&B']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Alt-country']))->toMatchArray(['genre' => 'Country']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Bossa nova']))->toMatchArray(['genre' => 'Jazz']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Chicago blues']))->toMatchArray(['genre' => 'Blues']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Drum and bass']))->toMatchArray(['genre' => 'Electronic']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Singer-songwriter']))->toMatchArray(['genre' => 'Singer/Songwriter']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Dancehall']))->toMatchArray(['genre' => 'Reggae']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Reggaeton']))->toMatchArray(['genre' => 'Latin']);
    expect($sanitizeMetadata->invoke($service, ['genre' => 'Baroque']))->toMatchArray(['genre' => 'Classical']);
});

it('accepts only allowed gemini theme values', function () {
    $service = new GeminiSongService;

    $sanitizeMetadata = (new ReflectionClass($service))
        ->getMethod('sanitizeMetadata');

    expect($sanitizeMetadata->invoke($service, ['theme' => 'Love']))->toMatchArray([
        'theme' => 'love',
    ]);
    expect($sanitizeMetadata->invoke($service, ['theme' => 'party']))->toMatchArray([
        'theme' => 'party',
    ]);
    expect($sanitizeMetadata->invoke($service, ['theme' => "St Patrick's"]))->toMatchArray([
        'theme' => 'st_patricks',
    ]);
    expect($sanitizeMetadata->invoke($service, ['theme' => 'not-allowed-theme']))->not->toHaveKey('theme');
});
