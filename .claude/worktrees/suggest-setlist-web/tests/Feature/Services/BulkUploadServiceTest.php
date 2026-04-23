<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Services\AccountUsageService;
use App\Services\BulkUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config()->set('filesystems.chart', 'public');
    Queue::fake();

    $this->usageService = mock(AccountUsageService::class)->shouldIgnoreMissing();
    $this->usageService->shouldReceive('storageLimitResponse')->andReturn(null);
    $this->usageService->shouldReceive('incrementStorageBytes');
    $this->usageService->shouldReceive('reserveBulkAiAllowance')
        ->andReturn(['allowed' => 1, 'remaining' => 9]);
    $this->usageService->shouldReceive('touchProjectActivity');
    app()->instance(AccountUsageService::class, $this->usageService);
});

it('uploads charts without creating Song or ProjectSong records', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $files = [
        UploadedFile::fake()->create('chart-a.pdf', 50, 'application/pdf'),
        UploadedFile::fake()->create('chart-b.pdf', 50, 'application/pdf'),
    ];

    $service = app(BulkUploadService::class);
    $result = $service->upload(
        files: $files,
        owner: $user,
        project: $project,
    );

    expect($result['uploaded'])->toBe(2);
    expect($result['charts'])->toHaveCount(2);
    expect(Chart::count())->toBe(2);
    expect(Song::count())->toBe(0);
    expect(ProjectSong::count())->toBe(0);
});

it('returns queued status when AI allowance is granted', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $file = UploadedFile::fake()->create('chart.pdf', 50, 'application/pdf');

    $service = app(BulkUploadService::class);
    $result = $service->upload(
        files: [$file],
        owner: $user,
        project: $project,
    );

    expect($result['queued'])->toBe(1);
    expect($result['charts'][0]['import_status'])->toBe('queued');
});

it('returns deferred status when AI allowance is exhausted', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    // Override the mock for this test
    $usageService = mock(AccountUsageService::class)->shouldIgnoreMissing();
    $usageService->shouldReceive('storageLimitResponse')->andReturn(null);
    $usageService->shouldReceive('incrementStorageBytes');
    $usageService->shouldReceive('reserveBulkAiAllowance')
        ->andReturn(['allowed' => 0, 'remaining' => 0]);
    $usageService->shouldReceive('touchProjectActivity');
    app()->instance(AccountUsageService::class, $usageService);

    $file = UploadedFile::fake()->create('chart.pdf', 50, 'application/pdf');

    $service = app(BulkUploadService::class);
    $result = $service->upload(
        files: [$file],
        owner: $user,
        project: $project,
    );

    expect($result['queued'])->toBe(0);
    expect($result['charts'][0]['import_status'])->toBe('deferred');
});

it('parses title and artist from filename into import_metadata', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $file = UploadedFile::fake()->create('Wonderwall - Oasis.pdf', 50, 'application/pdf');

    $service = app(BulkUploadService::class);
    $result = $service->upload(
        files: [$file],
        owner: $user,
        project: $project,
    );

    expect($result['charts'][0]['import_metadata']['title'])->toBe('Wonderwall');
    expect($result['charts'][0]['import_metadata']['artist'])->toBe('Oasis');

    $chart = Chart::first();
    expect($chart->import_metadata['title'])->toBe('Wonderwall');
    expect($chart->import_metadata['artist'])->toBe('Oasis');
});

it('parses theme from filename metadata', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $file = UploadedFile::fake()->create('My Song - My Artist -- theme: love.pdf', 50, 'application/pdf');

    $service = app(BulkUploadService::class);
    $result = $service->upload(
        files: [$file],
        owner: $user,
        project: $project,
    );

    expect($result['charts'][0]['import_metadata']['theme'])->toBe('love');
});

it('stores chart with null song_id', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    // Pre-create a song that matches the filename
    Song::findOrCreateByTitleAndArtist('Wonderwall', 'Oasis');

    $file = UploadedFile::fake()->create('Wonderwall - Oasis.pdf', 50, 'application/pdf');

    $service = app(BulkUploadService::class);
    $service->upload(
        files: [$file],
        owner: $user,
        project: $project,
    );

    $chart = Chart::first();
    // Upload does not link to songs - that happens at confirm time
    expect($chart->song_id)->toBeNull();
});

it('handles unparseable filenames gracefully', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $file = UploadedFile::fake()->create('random-file.pdf', 50, 'application/pdf');

    $service = app(BulkUploadService::class);
    $result = $service->upload(
        files: [$file],
        owner: $user,
        project: $project,
    );

    expect($result['charts'][0]['import_metadata']['title'])->toBeNull();
    expect($result['charts'][0]['import_metadata']['artist'])->toBeNull();
});
