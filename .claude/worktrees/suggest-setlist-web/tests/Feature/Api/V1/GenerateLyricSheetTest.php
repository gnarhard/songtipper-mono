<?php

declare(strict_types=1);

use App\Jobs\GenerateLyricSheet;
use App\Models\Chart;
use App\Models\Project;
use App\Models\Song;
use App\Models\User;
use App\Services\AccountUsageService;
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
    $this->song = Song::factory()->create([
        'title' => 'Country Roads',
        'artist' => 'John Denver',
    ]);
    Sanctum::actingAs($this->owner);
});

it('creates a chart with generating status and dispatches job', function () {
    $response = $this->postJson('/api/v1/me/charts/generate-lyrics', [
        'song_id' => $this->song->id,
        'project_id' => $this->project->id,
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('chart.source_type', 'ai_generated')
        ->assertJsonPath('chart.import_status', 'generating')
        ->assertJsonPath('chart.has_renders', false);

    $chart = Chart::query()->where('song_id', $this->song->id)->first();
    expect($chart)->not->toBeNull();
    expect($chart->source_type)->toBe('ai_generated');
    expect($chart->import_status)->toBe('generating');
    expect($chart->storage_path_pdf)->toBe('');

    Queue::assertPushed(GenerateLyricSheet::class, function ($job) use ($chart) {
        return $job->chartId === $chart->id
            && $job->title === 'Country Roads'
            && $job->artist === 'John Denver';
    });
});

it('returns 409 when chart already exists for song and project', function () {
    Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $this->song->id,
        'project_id' => $this->project->id,
    ]);

    $response = $this->postJson('/api/v1/me/charts/generate-lyrics', [
        'song_id' => $this->song->id,
        'project_id' => $this->project->id,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'chart_already_exists');

    Queue::assertNothingPushed();
});

it('returns 429 when AI quota is exceeded', function () {
    $mock = Mockery::mock(AccountUsageService::class)->makePartial();
    $mock->shouldReceive('aiInteractiveLimitResponse')
        ->once()
        ->andReturn([
            'error' => [
                'code' => 'ai_limit_exceeded',
                'message' => 'Monthly AI usage limit reached.',
            ],
        ]);

    $this->app->instance(AccountUsageService::class, $mock);

    $response = $this->postJson('/api/v1/me/charts/generate-lyrics', [
        'song_id' => $this->song->id,
        'project_id' => $this->project->id,
    ]);

    $response->assertStatus(429);

    Queue::assertNothingPushed();
});

it('returns 422 with invalid input', function () {
    $response = $this->postJson('/api/v1/me/charts/generate-lyrics', [
        'song_id' => 999999,
        'project_id' => $this->project->id,
    ]);

    $response->assertStatus(422);
    Queue::assertNothingPushed();
});

it('returns 404 when user lacks project access', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->create([
        'owner_user_id' => $otherUser->id,
    ]);

    $response = $this->postJson('/api/v1/me/charts/generate-lyrics', [
        'song_id' => $this->song->id,
        'project_id' => $otherProject->id,
    ]);

    $response->assertStatus(404);
    Queue::assertNothingPushed();
});
