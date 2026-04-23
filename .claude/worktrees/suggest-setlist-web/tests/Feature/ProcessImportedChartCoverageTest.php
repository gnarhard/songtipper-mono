<?php

declare(strict_types=1);

use App\Jobs\ProcessImportedChart;
use App\Models\Chart;
use App\Models\Project;
use App\Models\User;
use App\Services\AccountUsageService;
use App\Services\AiQuotaExceededException;
use App\Services\SongMetadataAiProvider;

it('returns early when chart is not found', function () {
    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldNotReceive('identifyAndEnrich');

    $job = new ProcessImportedChart(999999, 1);
    $job->handle($aiProvider);

    // Should complete without error
    expect(true)->toBeTrue();
});

it('returns early when project is not found', function () {
    $owner = User::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $owner->id,
    ]);

    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldNotReceive('identifyAndEnrich');

    $job = new ProcessImportedChart($chart->id, 999999);
    $job->handle($aiProvider);

    expect(true)->toBeTrue();
});

it('marks chart as failed when AI returns null', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $owner->id,
        'project_id' => $project->id,
        'song_id' => null,
    ]);

    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldReceive('identifyAndEnrich')->once()->andReturn(null);
    $aiProvider->shouldReceive('provider')->andReturn('test');

    $job = new ProcessImportedChart($chart->id, $project->id);
    $job->handle($aiProvider);

    $chart->refresh();
    expect($chart->import_status)->toBe('failed');
    expect($chart->import_error)->toBe('Could not identify song title and artist from this chart.');
});

it('handles failed callback when chart does not exist', function () {
    $job = new ProcessImportedChart(999999, 1);
    $job->failed(new RuntimeException('test error'));

    // Should complete without error
    expect(true)->toBeTrue();
});

it('returns unknown when provider throws exception', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $owner->id,
        'project_id' => $project->id,
        'song_id' => null,
    ]);

    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldReceive('identifyAndEnrich')->once()->andReturn([
        'title' => 'Test Song',
        'artist' => 'Test Artist',
    ]);
    $aiProvider->shouldReceive('provider')->andThrow(new RuntimeException('provider error'));

    $accountUsageService = mock(AccountUsageService::class);
    $accountUsageService->shouldReceive('recordAiOperation')
        ->once()
        ->withArgs(function ($user, $provider, $operation, $key) {
            return $provider === 'unknown';
        });

    $job = new ProcessImportedChart($chart->id, $project->id);
    $job->handle($aiProvider, $accountUsageService);

    $chart->refresh();
    expect($chart->import_status)->toBe('identified');
});

it('returns unknown when provider returns empty string', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $owner->id,
        'project_id' => $project->id,
        'song_id' => null,
    ]);

    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldReceive('identifyAndEnrich')->once()->andReturn([
        'title' => 'Another Test',
        'artist' => 'Another Artist',
    ]);
    $aiProvider->shouldReceive('provider')->andReturn('  ');

    $accountUsageService = mock(AccountUsageService::class);
    $accountUsageService->shouldReceive('recordAiOperation')
        ->once()
        ->withArgs(function ($user, $provider, $operation, $key) {
            return $provider === 'unknown';
        });

    $job = new ProcessImportedChart($chart->id, $project->id);
    $job->handle($aiProvider, $accountUsageService);

    $chart->refresh();
    expect($chart->import_status)->toBe('identified');
});

it('uses retryUntil to set the job timeout to 6 hours', function () {
    $job = new ProcessImportedChart(1, 1);
    $retryUntil = $job->retryUntil();

    expect($retryUntil)->toBeInstanceOf(DateTimeInterface::class);
    // Should be approximately 6 hours from now
    $diffMinutes = now()->diffInMinutes($retryUntil);
    expect($diffMinutes)->toBeGreaterThanOrEqual(359);
    expect($diffMinutes)->toBeLessThanOrEqual(361);
});

it('records quota-related failed callback with ai quota error message', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $owner->id,
        'project_id' => $project->id,
        'song_id' => null,
    ]);

    $job = new ProcessImportedChart($chart->id, $project->id);
    $job->failed(new AiQuotaExceededException('quota exhausted', 300));

    $chart->refresh();
    expect($chart->import_status)->toBe('failed');
    expect($chart->import_error)->toBe('Song identification service is temporarily unavailable. Please retry later.');
});
