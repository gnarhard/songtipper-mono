<?php

declare(strict_types=1);

use App\Enums\ChartTheme;
use App\Models\Chart;
use App\Models\ChartAnnotationVersion;
use App\Models\ChartRender;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $this->song = Song::factory()->create([
        'title' => 'Clone Source',
        'artist' => 'Source Artist',
    ]);
    $this->sourceProjectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'song_id' => $this->song->id,
        'version_label' => '',
        'notes' => 'Source notes',
        'capo' => 2,
    ]);
});

/**
 * Build a chart + render + saved annotation attached to `$sourceProjectSong`
 * so we can assert the copy carries over.
 */
function seedSourceChartWithAnnotation(User $owner, Project $project, Song $song, ProjectSong $projectSong): Chart
{
    $chart = Chart::factory()->create([
        'owner_user_id' => $owner->id,
        'project_id' => $project->id,
        'song_id' => $song->id,
        'project_song_id' => $projectSong->id,
        'storage_disk' => 'r2',
        'storage_path_pdf' => 'charts/shared/clone-source.pdf',
        'source_sha256' => hash('sha256', 'clone-source'),
        'original_filename' => 'clone-source.pdf',
        'page_count' => 2,
        'has_renders' => true,
    ]);
    ChartRender::factory()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Light,
        'storage_path_image' => 'charts/shared/clone-source/light/page-1.png',
    ]);
    ChartRender::factory()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Dark,
        'storage_path_image' => 'charts/shared/clone-source/dark/page-1.png',
    ]);

    DB::table('chart_annotation_versions')->insert([
        'chart_id' => $chart->id,
        'owner_user_id' => $owner->id,
        'page_number' => 1,
        'local_version_id' => 'source-version-1',
        'strokes' => json_encode([
            [
                'points' => [['x' => 0.1, 'y' => 0.2]],
                'color_value' => 4281545523,
                'thickness' => 2.5,
                'is_eraser' => false,
            ],
        ]),
        'client_created_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $chart;
}

it('creates a new alternate version with metadata only when charts are excluded', function () {
    Sanctum::actingAs($this->owner);

    seedSourceChartWithAnnotation($this->owner, $this->project, $this->song, $this->sourceProjectSong);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->sourceProjectSong->id}/clone",
        [
            'version_label' => 'Acoustic',
            'include_charts' => false,
            'include_annotations' => false,
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('copied_charts', 0)
        ->assertJsonPath('copied_annotations', 0)
        ->assertJsonPath('project_song.version_label', 'Acoustic')
        ->assertJsonPath('project_song.notes', 'Source notes')
        ->assertJsonPath('project_song.capo', 2);

    $clone = ProjectSong::query()
        ->where('project_id', $this->project->id)
        ->where('user_id', $this->owner->id)
        ->where('song_id', $this->song->id)
        ->where('version_label', 'Acoustic')
        ->first();

    expect($clone)->not->toBeNull();
    expect($clone?->id)->not->toBe($this->sourceProjectSong->id);

    expect(Chart::query()->where('project_song_id', $clone?->id)->count())->toBe(0);
});

it('copies charts (with renders) when include_charts=true but not annotations when flag is off', function () {
    Sanctum::actingAs($this->owner);

    $sourceChart = seedSourceChartWithAnnotation(
        $this->owner,
        $this->project,
        $this->song,
        $this->sourceProjectSong,
    );

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->sourceProjectSong->id}/clone",
        [
            'version_label' => 'Live',
            'include_charts' => true,
            'include_annotations' => false,
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('copied_charts', 1)
        ->assertJsonPath('copied_annotations', 0);

    $clone = ProjectSong::query()
        ->where('project_id', $this->project->id)
        ->where('user_id', $this->owner->id)
        ->where('song_id', $this->song->id)
        ->where('version_label', 'Live')
        ->first();

    expect($clone)->not->toBeNull();

    $clonedChart = Chart::query()->where('project_song_id', $clone?->id)->first();
    expect($clonedChart)->not->toBeNull();
    expect($clonedChart?->id)->not->toBe($sourceChart->id);
    expect($clonedChart?->storage_path_pdf)->toBe($sourceChart->storage_path_pdf);
    expect(ChartRender::query()->where('chart_id', $clonedChart?->id)->count())->toBe(2);

    // Annotations should not have been copied.
    expect(
        ChartAnnotationVersion::query()->where('chart_id', $clonedChart?->id)->count()
    )->toBe(0);
});

it('copies charts and annotations when both flags are true', function () {
    Sanctum::actingAs($this->owner);

    $sourceChart = seedSourceChartWithAnnotation(
        $this->owner,
        $this->project,
        $this->song,
        $this->sourceProjectSong,
    );

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->sourceProjectSong->id}/clone",
        [
            'version_label' => 'Solo',
            'include_charts' => true,
            'include_annotations' => true,
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('copied_charts', 1)
        ->assertJsonPath('copied_annotations', 1);

    $clone = ProjectSong::query()
        ->where('project_id', $this->project->id)
        ->where('user_id', $this->owner->id)
        ->where('song_id', $this->song->id)
        ->where('version_label', 'Solo')
        ->first();

    $clonedChart = Chart::query()->where('project_song_id', $clone?->id)->first();
    expect($clonedChart)->not->toBeNull();

    $clonedAnnotation = ChartAnnotationVersion::query()
        ->where('chart_id', $clonedChart?->id)
        ->where('owner_user_id', $this->owner->id)
        ->where('page_number', 1)
        ->first();

    expect($clonedAnnotation)->not->toBeNull();
    expect($clonedAnnotation?->local_version_id)->toBe('source-version-1');
    expect($clonedAnnotation?->strokes[0]['thickness'])->toBe(2.5);

    // Source annotation must remain untouched.
    expect(
        ChartAnnotationVersion::query()
            ->where('chart_id', $sourceChart->id)
            ->count()
    )->toBe(1);
});

it('defaults include_charts and include_annotations to true when omitted', function () {
    Sanctum::actingAs($this->owner);

    seedSourceChartWithAnnotation(
        $this->owner,
        $this->project,
        $this->song,
        $this->sourceProjectSong,
    );

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->sourceProjectSong->id}/clone",
        ['version_label' => 'Default']
    );

    $response->assertCreated()
        ->assertJsonPath('copied_charts', 1)
        ->assertJsonPath('copied_annotations', 1);
});

it('silently ignores include_annotations when include_charts=false', function () {
    Sanctum::actingAs($this->owner);

    seedSourceChartWithAnnotation(
        $this->owner,
        $this->project,
        $this->song,
        $this->sourceProjectSong,
    );

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->sourceProjectSong->id}/clone",
        [
            'version_label' => 'Chart-less',
            'include_charts' => false,
            'include_annotations' => true,
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('copied_charts', 0)
        ->assertJsonPath('copied_annotations', 0);
});

it('returns 409 when the version_label already exists for the caller', function () {
    Sanctum::actingAs($this->owner);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->owner->id,
        'song_id' => $this->song->id,
        'version_label' => 'Taken',
    ]);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->sourceProjectSong->id}/clone",
        ['version_label' => 'Taken']
    )->assertStatus(409);
});

it('rejects an empty version_label', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->sourceProjectSong->id}/clone",
        ['version_label' => '']
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['version_label']);
});

it('forbids cloning a project_song owned by another user', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    // Give otherUser access to the project but not the source project_song.
    ProjectMember::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $otherUser->id,
    ]);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->sourceProjectSong->id}/clone",
        ['version_label' => 'Stolen']
    )->assertForbidden();
});

it('requires authentication', function () {
    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/{$this->sourceProjectSong->id}/clone",
        ['version_label' => 'Acoustic']
    )->assertUnauthorized();
});
