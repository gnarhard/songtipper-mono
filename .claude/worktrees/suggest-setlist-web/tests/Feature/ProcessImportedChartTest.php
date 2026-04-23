<?php

declare(strict_types=1);

use App\Jobs\ProcessImportedChart;
use App\Models\Chart;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use App\Services\AiQuotaExceededException;
use App\Services\SongMetadataAiProvider;
use Mockery\MockInterface;

it('stores import_metadata on chart after identification', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $owner->id,
        'project_id' => $project->id,
        'song_id' => null,
    ]);

    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldReceive('identifyAndEnrich')
        ->once()
        ->withArgs(fn (Chart $arg): bool => $arg->is($chart))
        ->andReturn([
            'title' => 'Blinding Lights',
            'artist' => 'The Weeknd',
            'energy_level' => 'high',
            'era' => '2020s',
            'genre' => 'Pop',
            'original_musical_key' => 'F#m',
            'duration_in_seconds' => 200,
        ]);

    $job = new ProcessImportedChart($chart->id, $project->id);
    $job->handle($aiProvider);

    $chart->refresh();

    // Job now stores result in import_metadata instead of creating Song/ProjectSong
    expect($chart->import_metadata)->toBeArray();
    expect($chart->import_metadata['title'])->toBe('Blinding Lights');
    expect($chart->import_metadata['artist'])->toBe('The Weeknd');
    expect($chart->import_status)->toBe('identified');

    // Song and ProjectSong should NOT be created by the job
    expect($chart->song_id)->toBeNull();
    expect(ProjectSong::count())->toBe(0);
});

it('does not create Song or ProjectSong records', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    $existingSong = Song::factory()->create([
        'title' => 'Wonderwall',
        'artist' => 'Oasis',
        'normalized_key' => Song::generateNormalizedKey('Wonderwall', 'Oasis'),
    ]);

    $chart = Chart::factory()->create([
        'owner_user_id' => $owner->id,
        'project_id' => $project->id,
        'song_id' => null,
    ]);

    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldReceive('identifyAndEnrich')
        ->once()
        ->withArgs(fn (Chart $arg): bool => $arg->is($chart))
        ->andReturn([
            'title' => 'Wonderwall',
            'artist' => 'Oasis',
            'energy_level' => 'high',
            'era' => '2000s',
            'genre' => 'Pop',
            'original_musical_key' => 'A',
            'duration_in_seconds' => 180,
        ]);

    $job = new ProcessImportedChart($chart->id, $project->id);
    $job->handle($aiProvider);

    $chart->refresh();

    // Job stores metadata but does NOT link to Song
    expect($chart->import_metadata)->toBeArray();
    expect($chart->import_metadata['title'])->toBe('Wonderwall');
    expect($chart->import_metadata['artist'])->toBe('Oasis');
    expect($chart->import_status)->toBe('identified');
    expect($chart->song_id)->toBeNull();

    // No ProjectSong created
    expect(ProjectSong::query()->where([
        'project_id' => $project->id,
        'song_id' => $existingSong->id,
    ])->exists())->toBeFalse();
});

it('releases job when gemini quota is exhausted', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $owner->id,
        'project_id' => $project->id,
        'song_id' => null,
    ]);

    $aiProvider = mock(SongMetadataAiProvider::class);
    $aiProvider->shouldReceive('identifyAndEnrich')
        ->once()
        ->andThrow(new AiQuotaExceededException('quota exhausted', 120));

    /** @var ProcessImportedChart&MockInterface $job */
    $job = mock(ProcessImportedChart::class, [$chart->id, $project->id])->makePartial();
    $job->shouldReceive('release')->once()->with(120);

    $job->handle($aiProvider);

    $chart->refresh();
    expect($chart->song_id)->toBeNull();
    expect($chart->import_metadata)->toBeNull();
});

it('does not delete chart for quota-related failed callback', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $owner->id,
        'project_id' => $project->id,
        'song_id' => null,
    ]);

    $job = new ProcessImportedChart($chart->id, $project->id);
    $job->failed(new AiQuotaExceededException('quota exhausted', 300));

    expect(Chart::query()->whereKey($chart->id)->exists())->toBeTrue();
});

it('marks chart as failed for non-quota failures in failed callback', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $owner->id,
        'project_id' => $project->id,
        'song_id' => null,
    ]);

    $job = new ProcessImportedChart($chart->id, $project->id);
    $job->failed(new RuntimeException('permanent parse failure'));

    $chart->refresh();
    expect($chart->import_status)->toBe('failed');
    expect($chart->import_error)->toBe('permanent parse failure');
});
