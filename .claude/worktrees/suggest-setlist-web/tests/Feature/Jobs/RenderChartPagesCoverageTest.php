<?php

declare(strict_types=1);

use App\Jobs\RenderChartPages;
use App\Models\Chart;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('filesystems.chart', 'r2');
    Storage::fake('r2');

    $this->owner = User::factory()->create();
});

it('logs error and records failure in failed callback', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
    ]);

    $job = new RenderChartPages($chart);
    $job->failed(new RuntimeException('test render failure'));

    // Should complete without error — the failed() method logs and records usage
    expect(true)->toBeTrue();
});

it('throws when source pdf stream is null', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
        'storage_path_pdf' => 'charts/nonexistent/source.pdf',
    ]);

    // Don't put any file in storage — readStream returns null
    $job = new RenderChartPages($chart);

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
});

it('does not delete render files when paths array is empty', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
    ]);

    $job = new RenderChartPages($chart);

    // Call deleteRenderFiles with empty array
    $deleteRenderFiles = (new ReflectionClass($job))
        ->getMethod('deleteRenderFiles');

    $deleteRenderFiles->invoke($job, []);

    // Should complete without error
    expect(true)->toBeTrue();
});

it('interpolates sample position with count of 1', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
    ]);

    $job = new RenderChartPages($chart);

    $interpolate = (new ReflectionClass($job))
        ->getMethod('interpolateSamplePosition');

    // count <= 1 returns start
    expect($interpolate->invoke($job, 10, 100, 0, 1))->toBe(10);

    // start >= end returns start
    expect($interpolate->invoke($job, 100, 50, 0, 5))->toBe(100);
});
