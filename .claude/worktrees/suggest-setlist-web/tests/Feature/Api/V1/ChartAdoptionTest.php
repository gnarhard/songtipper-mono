<?php

declare(strict_types=1);

use App\Models\AccountUsageCounter;
use App\Models\Chart;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('filesystems.chart', 'r2');
    Storage::fake('r2');
    Queue::fake();

    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $this->song = Song::factory()->create();

    $this->pdfContent = 'fake-pdf-content-for-adoption-test';

    $this->sourceChart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
        'storage_disk' => 'r2',
        'storage_path_pdf' => "charts/{$this->owner->id}/source-chart/source.pdf",
        'source_sha256' => hash('sha256', $this->pdfContent),
        'original_filename' => 'my-chart.pdf',
        'file_size_bytes' => strlen($this->pdfContent),
        'has_renders' => true,
        'page_count' => 2,
    ]);

    Storage::disk('r2')->put($this->sourceChart->storage_path_pdf, $this->pdfContent);
});

it('adopts a chart successfully and returns 201', function () {
    $adopter = User::factory()->create();
    $this->project->members()->attach($adopter, ['role' => 'member']);
    Sanctum::actingAs($adopter);

    $response = $this->postJson("/api/v1/me/charts/{$this->sourceChart->id}/adopt");

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Chart adopted successfully.');

    $newChart = Chart::query()
        ->where('owner_user_id', $adopter->id)
        ->where('song_id', $this->song->id)
        ->where('project_id', $this->project->id)
        ->first();

    expect($newChart)->not->toBeNull();
    expect($newChart->id)->not->toBe($this->sourceChart->id);
    expect($newChart->owner_user_id)->toBe($adopter->id);
    expect($newChart->song_id)->toBe($this->song->id);
    expect($newChart->project_id)->toBe($this->project->id);
    expect($newChart->source_sha256)->toBe($this->sourceChart->source_sha256);
    expect($newChart->original_filename)->toBe($this->sourceChart->original_filename);
    expect((int) $newChart->file_size_bytes)->toBe(strlen($this->pdfContent));
});

it('returns 409 if adopter already has a chart for the same project song', function () {
    $adopter = User::factory()->create();
    $this->project->members()->attach($adopter, ['role' => 'member']);
    Sanctum::actingAs($adopter);

    Chart::factory()->create([
        'owner_user_id' => $adopter->id,
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
    ]);

    $response = $this->postJson("/api/v1/me/charts/{$this->sourceChart->id}/adopt");

    $response->assertStatus(409)
        ->assertJsonPath('message', 'You already have a chart for this song in this project.');
});

it('returns 404 if adopter is not a project member', function () {
    $outsider = User::factory()->create();
    Sanctum::actingAs($outsider);

    $response = $this->postJson("/api/v1/me/charts/{$this->sourceChart->id}/adopt");

    $response->assertNotFound();
});

it('copies the PDF to a new storage path', function () {
    $adopter = User::factory()->create();
    $this->project->members()->attach($adopter, ['role' => 'member']);
    Sanctum::actingAs($adopter);

    $response = $this->postJson("/api/v1/me/charts/{$this->sourceChart->id}/adopt");

    $response->assertStatus(201);

    $newChart = Chart::query()
        ->where('owner_user_id', $adopter->id)
        ->where('song_id', $this->song->id)
        ->where('project_id', $this->project->id)
        ->first();

    $expectedPath = "charts/{$adopter->id}/{$newChart->id}/source.pdf";
    expect($newChart->storage_path_pdf)->toBe($expectedPath);

    Storage::disk('r2')->assertExists($expectedPath);
    expect(Storage::disk('r2')->get($expectedPath))->toBe($this->pdfContent);

    // Source chart PDF should still exist
    Storage::disk('r2')->assertExists($this->sourceChart->storage_path_pdf);
});

it('increments storage bytes for the adopter', function () {
    $adopter = User::factory()->create();
    $this->project->members()->attach($adopter, ['role' => 'member']);
    Sanctum::actingAs($adopter);

    // Ensure counter exists with a known baseline
    $counter = AccountUsageCounter::query()->firstOrCreate(
        ['user_id' => $adopter->id],
        ['storage_bytes' => 0, 'chart_pdf_bytes' => 0, 'last_activity_at' => now()],
    );
    $initialStorageBytes = (int) $counter->storage_bytes;
    $initialChartPdfBytes = (int) $counter->chart_pdf_bytes;

    $response = $this->postJson("/api/v1/me/charts/{$this->sourceChart->id}/adopt");

    $response->assertStatus(201);

    $counter->refresh();
    $expectedFileSize = strlen($this->pdfContent);
    expect((int) $counter->storage_bytes)->toBe($initialStorageBytes + $expectedFileSize);
    expect((int) $counter->chart_pdf_bytes)->toBe($initialChartPdfBytes + $expectedFileSize);
});

it('returns 200 when adopter already owns the chart', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/charts/{$this->sourceChart->id}/adopt");

    $response->assertOk()
        ->assertJsonPath('message', 'You already own this chart.')
        ->assertJsonPath('chart.id', $this->sourceChart->id);
});

it('links the adopted chart back to the project song via project_song_id', function () {
    $adopter = User::factory()->create();
    $this->project->members()->attach($adopter, ['role' => 'member']);
    Sanctum::actingAs($adopter);

    // Give the source chart a valid project_song_id
    $projectSong = ProjectSong::query()->firstOrCreate([
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
    ]);
    $this->sourceChart->update(['project_song_id' => $projectSong->id]);

    $response = $this->postJson("/api/v1/me/charts/{$this->sourceChart->id}/adopt");

    $response->assertStatus(201);

    $newChart = Chart::query()
        ->where('owner_user_id', $adopter->id)
        ->where('song_id', $this->song->id)
        ->first();

    expect($newChart)->not->toBeNull();
    expect($newChart->project_song_id)->toBe($projectSong->id);
});
