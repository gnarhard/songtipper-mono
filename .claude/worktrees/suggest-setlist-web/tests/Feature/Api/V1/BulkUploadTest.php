<?php

declare(strict_types=1);

use App\Jobs\ProcessImportedChart;
use App\Jobs\RenderChartPages;
use App\Models\Chart;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use App\Services\AccountUsageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('r2');

    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);

    // Mock AccountUsageService with all methods needed across the request lifecycle
    $this->usageService = mock(AccountUsageService::class)->shouldIgnoreMissing();
    $this->usageService->shouldReceive('storageLimitResponse')->andReturn(null);
    $this->usageService->shouldReceive('reserveBulkAiAllowance')
        ->andReturn(['allowed' => 1, 'remaining' => 9]);
    app()->instance(AccountUsageService::class, $this->usageService);
});

it('uploads a single file and queues for identification', function () {
    Queue::fake();
    Sanctum::actingAs($this->owner);

    $file = UploadedFile::fake()->create('random-chart.pdf', 100, 'application/pdf');

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", [
        'files' => [$file],
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'charts' => [
                    ['chart_id', 'filename', 'import_status', 'import_metadata'],
                ],
                'message',
            ],
        ]);

    $charts = $response->json('data.charts');
    expect($charts)->toHaveCount(1);
    expect($charts[0]['filename'])->toBe('random-chart.pdf');
    expect($charts[0]['import_status'])->toBe('queued');

    expect(Chart::count())->toBe(1);
    expect(Chart::first()->song_id)->toBeNull();
    expect(Song::count())->toBe(0);
    expect(ProjectSong::count())->toBe(0);

    Queue::assertPushed(ProcessImportedChart::class, 1);
    Queue::assertNotPushed(RenderChartPages::class);
});

it('uploads multiple files and queues each for identification', function () {
    Queue::fake();
    Sanctum::actingAs($this->owner);

    $files = [
        UploadedFile::fake()->create('chart1.pdf', 100, 'application/pdf'),
        UploadedFile::fake()->create('chart2.pdf', 100, 'application/pdf'),
        UploadedFile::fake()->create('chart3.pdf', 100, 'application/pdf'),
    ];

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", [
        'files' => $files,
    ]);

    $response->assertSuccessful();

    $charts = $response->json('data.charts');
    expect($charts)->toHaveCount(3);

    expect(Chart::count())->toBe(3);
    expect(Song::count())->toBe(0);
    expect(ProjectSong::count())->toBe(0);

    Queue::assertPushed(ProcessImportedChart::class, 3);
    Queue::assertNotPushed(RenderChartPages::class);
});

it('returns chart summary with filename and import_metadata for each file', function () {
    Queue::fake();
    Sanctum::actingAs($this->owner);

    $file = UploadedFile::fake()->create('Wonderwall - Oasis.pdf', 100, 'application/pdf');

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", [
        'files' => [$file],
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'charts' => [
                    ['chart_id', 'filename', 'import_status', 'import_metadata'],
                ],
                'message',
            ],
        ]);

    $charts = $response->json('data.charts');
    expect($charts[0]['filename'])->toBe('Wonderwall - Oasis.pdf');
    expect($charts[0]['import_metadata']['title'])->toBe('Wonderwall');
    expect($charts[0]['import_metadata']['artist'])->toBe('Oasis');
});

it('stores parsed filename metadata on the chart record', function () {
    Queue::fake();
    Sanctum::actingAs($this->owner);

    $file = UploadedFile::fake()->create('Wonderwall - Oasis -- theme: love.pdf', 100, 'application/pdf');

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", [
        'files' => [$file],
    ]);

    $response->assertSuccessful();

    $chart = Chart::first();
    expect($chart->import_metadata)->toBeArray();
    expect($chart->import_metadata['title'])->toBe('Wonderwall');
    expect($chart->import_metadata['artist'])->toBe('Oasis');
    expect($chart->import_metadata['theme'])->toBe('love');
});

it('uploads chart to storage', function () {
    Queue::fake();
    Sanctum::actingAs($this->owner);

    $file = UploadedFile::fake()->create('chart.pdf', 100, 'application/pdf');

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", [
        'files' => [$file],
    ]);

    $response->assertSuccessful();

    $chart = Chart::first();
    expect($chart->storage_path_pdf)->toContain("charts/{$this->owner->id}");
    Storage::disk(config('filesystems.chart'))->assertExists($chart->storage_path_pdf);
});

it('does not create Song or ProjectSong records during upload', function () {
    Queue::fake();
    Sanctum::actingAs($this->owner);

    Song::factory()->create([
        'title' => 'Wonderwall',
        'artist' => 'Oasis',
        'normalized_key' => Song::generateNormalizedKey('Wonderwall', 'Oasis'),
    ]);

    $file = UploadedFile::fake()->create('Wonderwall - Oasis.pdf', 100, 'application/pdf');

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", [
        'files' => [$file],
    ]);

    $response->assertSuccessful();

    $chart = Chart::first();
    expect($chart->song_id)->toBeNull();
    expect(ProjectSong::count())->toBe(0);
});

it('returns 404 when user has no access to the project', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $file = UploadedFile::fake()->create('chart.pdf', 100, 'application/pdf');

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", [
        'files' => [$file],
    ]);

    $response->assertNotFound();
});

it('requires authentication', function () {
    $file = UploadedFile::fake()->create('chart.pdf', 100, 'application/pdf');

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", [
        'files' => [$file],
    ]);

    $response->assertUnauthorized();
});

it('requires chunking when more than twenty files are submitted', function () {
    Queue::fake();
    Sanctum::actingAs($this->owner);

    $files = collect(range(1, 21))
        ->map(
            fn (int $index): UploadedFile => UploadedFile::fake()->create(
                "chart-{$index}.pdf",
                100,
                'application/pdf',
            )
        )
        ->all();

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", [
        'files' => $files,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);

    expect($response->json('errors.files.0'))->toContain('Upload up to 20 files');
    Queue::assertNothingPushed();
});

it('rejects PDFs larger than two megabytes', function () {
    Queue::fake();
    Sanctum::actingAs($this->owner);

    $file = UploadedFile::fake()->create('too-large.pdf', 2050, 'application/pdf');

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", [
        'files' => [$file],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0']);

    Queue::assertNothingPushed();
});

it('requires files to be provided', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);
});

it('passes existing_songs_only flag to service', function () {
    Queue::fake();
    Sanctum::actingAs($this->owner);

    $file = UploadedFile::fake()->create('chart.pdf', 100, 'application/pdf');

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload", [
        'files' => [$file],
        'existing_songs_only' => true,
    ]);

    $response->assertSuccessful();
});
