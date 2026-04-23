<?php

declare(strict_types=1);

use App\Services\AiQuotaExceededException;
use App\Services\ClaudeSongService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('uses anthropic messages endpoint with expected headers and payload', function () {
    config()->set('services.anthropic.api_key', 'test-claude-key');

    config()->set('services.anthropic.model', 'claude-sonnet-4-6');
    config()->set('services.anthropic.timeout_seconds', 5);

    Http::fake([
        '*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => '{"energy_level":"high","genre":"Rock","duration_in_seconds":240}',
            ]],
        ], 200),
    ]);

    $service = new ClaudeSongService;

    $callClaude = (new ReflectionClass($service))
        ->getMethod('callClaude');

    $result = $callClaude->invoke(
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
        expect($request->url())->toContain('/v1/messages');
        expect($request->hasHeader('x-api-key', 'test-claude-key'))->toBeTrue();
        expect($request->hasHeader('anthropic-version', '2023-06-01'))->toBeTrue();

        $payload = $request->data();
        expect($payload['model'] ?? null)->toBe('claude-sonnet-4-6');
        expect($payload['max_tokens'] ?? null)->toBe(1024);
        expect($payload['messages'][0]['content'][1]['type'] ?? null)->toBe('image');
        expect($payload['messages'][0]['content'][1]['source']['type'] ?? null)->toBe('base64');

        return true;
    });
});

it('reports as disabled when claude api key is missing', function () {
    config()->set('services.anthropic.api_key', '');

    $service = new ClaudeSongService;
    expect($service->isEnabled())->toBeFalse();
});

it('strips markdown code fences from claude response', function () {
    config()->set('services.anthropic.api_key', 'test-claude-key');

    config()->set('services.anthropic.model', 'claude-sonnet-4-6');

    Http::fake([
        '*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => "```json\n{\"energy_level\":\"low\"}\n```",
            ]],
        ], 200),
    ]);

    $service = new ClaudeSongService;

    $callClaude = (new ReflectionClass($service))
        ->getMethod('callClaude');

    $result = $callClaude->invoke(
        $service,
        'Test prompt',
        base64_encode('fake-image-bytes')
    );

    expect($result)->toBe(['energy_level' => 'low']);
});

it('throws quota exception on 401 authentication error', function () {
    config()->set('services.anthropic.api_key', 'invalid-key');

    config()->set('services.anthropic.model', 'claude-sonnet-4-6');

    Http::fake([
        '*' => Http::response([
            'type' => 'error',
            'error' => [
                'type' => 'authentication_error',
                'message' => 'Invalid authentication credentials',
            ],
        ], 401),
    ]);

    $service = new ClaudeSongService;

    $callClaude = (new ReflectionClass($service))
        ->getMethod('callClaude');

    $callClaude->invoke($service, 'Test prompt');
})->throws(AiQuotaExceededException::class, 'Anthropic API authentication failed');

it('normalizes claude theme values to canonical enum values', function () {
    $service = new ClaudeSongService;

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
