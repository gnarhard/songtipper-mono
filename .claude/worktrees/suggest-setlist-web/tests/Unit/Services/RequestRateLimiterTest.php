<?php

declare(strict_types=1);

use App\Models\Project;
use App\Services\RequestRateLimiter;
use Illuminate\Support\Facades\RateLimiter;

it('returns remaining attempts for a project song and ip', function () {
    $project = new Project;
    $project->id = 42;

    $limiter = new RequestRateLimiter;

    RateLimiter::shouldReceive('remaining')
        ->once()
        ->withArgs(function (string $key, int $max) {
            return str_contains($key, 'song_request:42:7:') && $max === 3;
        })
        ->andReturn(2);

    $result = $limiter->remainingAttempts($project, 7, '127.0.0.1');

    expect($result)->toBe(2);
});

it('returns remaining attempts with null ip', function () {
    $project = new Project;
    $project->id = 10;

    $limiter = new RequestRateLimiter;

    RateLimiter::shouldReceive('remaining')
        ->once()
        ->withArgs(function (string $key, int $max) {
            return str_contains($key, 'unknown') && $max === 3;
        })
        ->andReturn(3);

    $result = $limiter->remainingAttempts($project, 5);

    expect($result)->toBe(3);
});
