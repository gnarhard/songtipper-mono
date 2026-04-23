<?php

declare(strict_types=1);

use App\Services\OpenAiSongService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('uses chat completions endpoint with json response format', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    config()->set('services.openai.model', 'gpt-4o-mini');
    config()->set('services.openai.timeout_seconds', 5);

    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => '{"energy_level":"high","genre":"Rock","duration_in_seconds":240}',
                ],
            ]],
        ], 200),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $result = $callOpenAi->invoke(
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
        expect($request->url())->toContain('/v1/chat/completions');
        expect($request->hasHeader('Authorization'))->toBeTrue();

        $payload = $request->data();
        expect($payload['model'] ?? null)->toBe('gpt-4o-mini');
        expect($payload['response_format']['type'] ?? null)->toBe('json_object');
        expect($payload['messages'][0]['content'][1]['type'] ?? null)->toBe('image_url');

        return true;
    });
});

it('reports as disabled when openai api key is missing', function () {
    config()->set('services.openai.api_key', '');

    $service = new OpenAiSongService;
    expect($service->isEnabled())->toBeFalse();
});

it('strips markdown code fences from openai response', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    config()->set('services.openai.model', 'gpt-4o-mini');

    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => "```json\n{\"energy_level\":\"low\"}\n```",
                ],
            ]],
        ], 200),
    ]);

    $service = new OpenAiSongService;

    $callOpenAi = (new ReflectionClass($service))
        ->getMethod('callOpenAi');

    $result = $callOpenAi->invoke(
        $service,
        'Test prompt',
        base64_encode('fake-image-bytes')
    );

    expect($result)->toBe(['energy_level' => 'low']);
});

it('normalizes openai theme values to canonical enum values', function () {
    $service = new OpenAiSongService;

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
