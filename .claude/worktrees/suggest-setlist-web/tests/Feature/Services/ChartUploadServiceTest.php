<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\ChartRender;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Services\ChartUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config()->set('filesystems.chart', 'public');
});

it('uploads a new chart for a song', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);
    $song = Song::factory()->create();
    ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);

    $file = UploadedFile::fake()->create('test-chart.pdf', 100, 'application/pdf');

    $service = app(ChartUploadService::class);
    $result = $service->upload($file, $user, $song, $project);

    expect($result->status)->toBe('created')
        ->and($result->chart->song_id)->toBe($song->id)
        ->and($result->chart->owner_user_id)->toBe($user->id);
});

it('replaces existing chart when source differs', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);
    $song = Song::factory()->create();
    ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);

    $file1 = UploadedFile::fake()->create('chart-v1.pdf', 100, 'application/pdf');
    $service = app(ChartUploadService::class);
    $result1 = $service->upload($file1, $user, $song, $project);

    // Upload a different file for same song
    $file2 = UploadedFile::fake()->createWithContent('chart-v2.pdf', 'different-content-'.uniqid());
    $result2 = $service->upload($file2, $user, $song, $project);

    expect($result2->status)->toBe('replaced')
        ->and($result2->chart->id)->toBe($result1->chart->id);
});

it('returns unchanged when same file is uploaded again', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);
    $song = Song::factory()->create();
    ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);

    $content = 'consistent-pdf-content-'.uniqid();
    $file1 = UploadedFile::fake()->createWithContent('chart.pdf', $content);
    $service = app(ChartUploadService::class);
    $result1 = $service->upload($file1, $user, $song, $project);

    $file2 = UploadedFile::fake()->createWithContent('chart.pdf', $content);
    $result2 = $service->upload($file2, $user, $song, $project);

    expect($result2->status)->toBe('unchanged');
});

it('uploads chart without song', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $file = UploadedFile::fake()->create('unknown-chart.pdf', 50, 'application/pdf');

    $service = app(ChartUploadService::class);
    $result = $service->upload($file, $user, null, $project);

    expect($result->status)->toBe('created')
        ->and($result->chart->song_id)->toBeNull();
});

it('uploads chart with project id instead of model', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $file = UploadedFile::fake()->create('chart.pdf', 50, 'application/pdf');

    $service = app(ChartUploadService::class);
    $result = $service->upload($file, $user, null, $project->id);

    expect($result->status)->toBe('created');
});

it('deletes a chart and its files', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);
    $song = Song::factory()->create();

    $file = UploadedFile::fake()->create('chart.pdf', 50, 'application/pdf');

    $service = app(ChartUploadService::class);
    $result = $service->upload($file, $user, $song, $project);
    $chart = $result->chart;

    $deleted = $service->delete($chart);

    expect($deleted)->toBeTrue()
        ->and(Chart::find($chart->id))->toBeNull();
});

it('skips activity touch when requested', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $file = UploadedFile::fake()->create('chart.pdf', 50, 'application/pdf');

    $service = app(ChartUploadService::class);
    $result = $service->upload($file, $user, null, $project, skipActivityTouch: true);

    expect($result->status)->toBe('created');
});

it('cleans up renders when replacing chart', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);
    $song = Song::factory()->create();
    ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);

    $file1 = UploadedFile::fake()->create('chart.pdf', 100, 'application/pdf');
    $service = app(ChartUploadService::class);
    $result1 = $service->upload($file1, $user, $song, $project);

    // Create a render for the chart
    ChartRender::factory()->create([
        'chart_id' => $result1->chart->id,
        'storage_path_image' => 'charts/renders/page1.png',
    ]);

    // Upload different file to replace
    $file2 = UploadedFile::fake()->createWithContent('chart-v2.pdf', 'new-content-'.uniqid());
    $result2 = $service->upload($file2, $user, $song, $project);

    expect($result2->status)->toBe('replaced')
        ->and(ChartRender::where('chart_id', $result1->chart->id)->count())->toBe(0);
});
