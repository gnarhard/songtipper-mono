<?php

declare(strict_types=1);

use App\Enums\ChartTheme;
use App\Models\Chart;
use App\Models\ChartRender;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('filesystems.chart', 'r2');
    Storage::fake('r2');

    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    Sanctum::actingAs($this->owner);
});

it('returns a signed url for an existing chart page render', function () {
    $chart = Chart::factory()->withRenders(2)->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
    ]);

    $storagePath = "charts/{$this->owner->id}/{$chart->id}/renders/light/page-1.png";
    Storage::disk('r2')->put($storagePath, 'fake-image-data');

    ChartRender::factory()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Light,
        'storage_path_image' => $storagePath,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/page?page=1&theme=light");

    $response->assertOk()
        ->assertJsonStructure(['url']);

    expect($response->json('url'))->toBeString()->not->toBeEmpty();
});

it('returns a signed url for dark theme', function () {
    $chart = Chart::factory()->withRenders(1)->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
    ]);

    $storagePath = "charts/{$this->owner->id}/{$chart->id}/renders/dark/page-1.png";
    Storage::disk('r2')->put($storagePath, 'fake-image-data');

    ChartRender::factory()->dark()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'storage_path_image' => $storagePath,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/page?page=1&theme=dark");

    $response->assertOk()
        ->assertJsonStructure(['url']);
});

it('falls back to another available theme for the same page when requested theme is missing', function () {
    $chart = Chart::factory()->withRenders(1)->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
    ]);

    $darkStoragePath = "charts/{$this->owner->id}/{$chart->id}/renders/dark/page-1.png";
    Storage::disk('r2')->put($darkStoragePath, 'fake-dark-image-data');

    ChartRender::factory()->dark()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'storage_path_image' => $darkStoragePath,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/page?page=1&theme=light");

    $response->assertOk()
        ->assertJsonPath('requested_theme', 'light')
        ->assertJsonPath('served_theme', 'dark')
        ->assertJsonStructure(['url']);
});

it('returns 404 when render does not exist for page', function () {
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/page?page=1&theme=light");

    $response->assertNotFound();
});

it('returns 422 when page parameter is missing', function () {
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/page?theme=light");

    $response->assertUnprocessable();
});

it('returns 422 when theme parameter is invalid', function () {
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/page?page=1&theme=invalid");

    $response->assertUnprocessable();
});

it('returns 403 when accessing another users chart', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->create([
        'owner_user_id' => $otherUser->id,
    ]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $otherUser->id,
        'project_id' => $otherProject->id,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/page?page=1&theme=light");

    $response->assertForbidden();
});
