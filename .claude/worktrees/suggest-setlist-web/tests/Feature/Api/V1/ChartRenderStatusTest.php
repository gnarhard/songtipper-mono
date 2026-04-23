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

it('returns ready status when all render files exist', function () {
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'has_renders' => true,
        'page_count' => 1,
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, 'pdf-bytes');

    $lightPath = "charts/{$this->owner->id}/{$chart->id}/renders/light/page-1.png";
    $darkPath = "charts/{$this->owner->id}/{$chart->id}/renders/dark/page-1.png";

    Storage::disk('r2')->put($lightPath, 'light');
    Storage::disk('r2')->put($darkPath, 'dark');

    ChartRender::factory()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Light,
        'storage_path_image' => $lightPath,
    ]);
    ChartRender::factory()->dark()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'storage_path_image' => $darkPath,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/render-status");

    $response->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('ready', true)
        ->assertJsonPath('pending', false)
        ->assertJsonPath('render_count', 2)
        ->assertJsonPath('expected_render_count', 2)
        ->assertJsonPath('failure_reason', null);
});

it('returns pending status when renders are not ready yet', function () {
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'has_renders' => false,
        'page_count' => 0,
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, 'pdf-bytes');

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/render-status");

    $response->assertOk()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('ready', false)
        ->assertJsonPath('pending', true)
        ->assertJsonPath('failure_reason', null);
});

it('returns failed when render metadata is inconsistent', function () {
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'has_renders' => true,
        'page_count' => 0,
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, 'pdf-bytes');

    $lightPath = "charts/{$this->owner->id}/{$chart->id}/renders/light/page-1.png";
    $darkPath = "charts/{$this->owner->id}/{$chart->id}/renders/dark/page-1.png";

    Storage::disk('r2')->put($lightPath, 'light');
    Storage::disk('r2')->put($darkPath, 'dark');

    ChartRender::factory()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Light,
        'storage_path_image' => $lightPath,
    ]);
    ChartRender::factory()->dark()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'storage_path_image' => $darkPath,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/render-status");

    $response->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('ready', false)
        ->assertJsonPath('pending', false)
        ->assertJsonPath('render_count', 2)
        ->assertJsonPath('expected_render_count', null)
        ->assertJsonPath('failure_reason', 'render_metadata_inconsistent');
});

it('returns failed when render rows exist but has_renders is false', function () {
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'has_renders' => false,
        'page_count' => 1,
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, 'pdf-bytes');

    $lightPath = "charts/{$this->owner->id}/{$chart->id}/renders/light/page-1.png";
    $darkPath = "charts/{$this->owner->id}/{$chart->id}/renders/dark/page-1.png";

    Storage::disk('r2')->put($lightPath, 'light');
    Storage::disk('r2')->put($darkPath, 'dark');

    ChartRender::factory()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Light,
        'storage_path_image' => $lightPath,
    ]);
    ChartRender::factory()->dark()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'storage_path_image' => $darkPath,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/render-status");

    $response->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('ready', false)
        ->assertJsonPath('pending', false)
        ->assertJsonPath('failure_reason', 'render_metadata_inconsistent');
});

it('returns failed status when source pdf is missing', function () {
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'has_renders' => false,
        'page_count' => 0,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/render-status");

    $response->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('ready', false)
        ->assertJsonPath('pending', false)
        ->assertJsonPath('failure_reason', 'source_pdf_missing');
});

it('returns failed status when a render file is missing from storage', function () {
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'has_renders' => true,
        'page_count' => 1,
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, 'pdf-bytes');

    $lightPath = "charts/{$this->owner->id}/{$chart->id}/renders/light/page-1.png";
    $darkPath = "charts/{$this->owner->id}/{$chart->id}/renders/dark/page-1.png";

    Storage::disk('r2')->put($lightPath, 'light');

    ChartRender::factory()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Light,
        'storage_path_image' => $lightPath,
    ]);
    ChartRender::factory()->dark()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'storage_path_image' => $darkPath,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/render-status?verify_files=true");

    $response->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('failure_reason', 'render_file_missing')
        ->assertJsonPath('missing_render_file_count', 1);
});

it('returns 403 when requesting render status for another users chart', function () {
    $otherOwner = User::factory()->create();
    $otherProject = Project::factory()->create([
        'owner_user_id' => $otherOwner->id,
    ]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $otherOwner->id,
        'project_id' => $otherProject->id,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$chart->id}/render-status");

    $response->assertForbidden();
});
