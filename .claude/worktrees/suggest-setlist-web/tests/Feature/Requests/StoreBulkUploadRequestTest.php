<?php

declare(strict_types=1);

use App\Models\Project;
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
    Sanctum::actingAs($this->owner);
});

it('rejects when no files are provided', function () {
    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload",
        []
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);
});

it('rejects empty files array', function () {
    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload",
        ['files' => []]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);
});

it('rejects non-PDF files', function () {
    $file = UploadedFile::fake()->create('chart.txt', 100, 'text/plain');

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload",
        ['files' => [$file]]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0']);
});

it('rejects pdf-extension files with a non-pdf declared mimetype', function () {
    // Defends against the case where the filename extension is faked but
    // the underlying MIME type does not match application/pdf.
    $file = UploadedFile::fake()->create('fake.pdf', 100, 'text/plain');

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload",
        ['files' => [$file]]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0']);
});

it('rejects files larger than 2MB', function () {
    $file = UploadedFile::fake()->create('large.pdf', 2050, 'application/pdf');

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload",
        ['files' => [$file]]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0']);
});

it('rejects more than 20 files', function () {
    $files = collect(range(1, 21))
        ->map(fn ($i) => UploadedFile::fake()->create("chart-{$i}.pdf", 50, 'application/pdf'))
        ->all();

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload",
        ['files' => $files]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);
});

it('accepts valid PDF files', function () {
    Queue::fake();

    $usageService = mock(AccountUsageService::class)->shouldIgnoreMissing();
    $usageService->shouldReceive('storageLimitResponse')->andReturn(null);
    $usageService->shouldReceive('reserveBulkAiAllowance')
        ->andReturn(['allowed' => 1, 'remaining' => 9]);
    app()->instance(AccountUsageService::class, $usageService);

    $file = UploadedFile::fake()->create('chart.pdf', 100, 'application/pdf');

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload",
        ['files' => [$file]]
    );

    $response->assertSuccessful();
});

it('accepts existing_songs_only boolean parameter', function () {
    Queue::fake();

    $usageService = mock(AccountUsageService::class)->shouldIgnoreMissing();
    $usageService->shouldReceive('storageLimitResponse')->andReturn(null);
    $usageService->shouldReceive('reserveBulkAiAllowance')
        ->andReturn(['allowed' => 1, 'remaining' => 9]);

    $file = UploadedFile::fake()->create('chart.pdf', 100, 'application/pdf');

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-upload",
        [
            'files' => [$file],
            'existing_songs_only' => true,
        ]
    );

    $response->assertSuccessful();
});
