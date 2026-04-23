<?php

declare(strict_types=1);

use App\Enums\ChartTheme;
use App\Models\Chart;
use App\Models\ChartAnnotationVersion;
use App\Models\ChartRender;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->sourceProject = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $this->destinationProject = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
});

it('copies only the selected source repertoire songs', function () {
    Sanctum::actingAs($this->owner);

    $selectedSong = Song::factory()->create([
        'title' => 'Selected Song',
        'artist' => 'Selected Artist',
    ]);
    $unselectedSong = Song::factory()->create([
        'title' => 'Skipped Song',
        'artist' => 'Skipped Artist',
    ]);

    $selectedProjectSong = ProjectSong::factory()->create([
        'project_id' => $this->sourceProject->id,
        'song_id' => $selectedSong->id,
        'instrumental' => true,
        'genre' => 'Rock',
        'theme' => 'party',
        'capo' => 2,
        'notes' => 'Fingerpick the intro softly.',
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->sourceProject->id,
        'song_id' => $unselectedSong->id,
        'genre' => 'Pop',
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->destinationProject->id}/repertoire/copy-from",
        [
            'source_project_id' => $this->sourceProject->id,
            'source_project_song_ids' => [$selectedProjectSong->id],
            'include_charts' => false,
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('data.copied_songs', 1)
        ->assertJsonPath('data.copied_charts', 0);

    $this->assertDatabaseHas('project_songs', [
        'project_id' => $this->destinationProject->id,
        'song_id' => $selectedSong->id,
        'instrumental' => true,
        'genre' => 'Rock',
        'theme' => 'party',
        'capo' => 2,
        'notes' => 'Fingerpick the intro softly.',
    ]);
    $this->assertDatabaseMissing('project_songs', [
        'project_id' => $this->destinationProject->id,
        'song_id' => $unselectedSong->id,
    ]);
    $this->assertDatabaseMissing('charts', [
        'project_id' => $this->destinationProject->id,
        'song_id' => $selectedSong->id,
    ]);
});

it('copies linked charts and renders for selected songs when requested', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create([
        'title' => 'Charted Song',
        'artist' => 'Chart Artist',
    ]);
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $this->sourceProject->id,
        'song_id' => $song->id,
    ]);

    $sourceChart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->sourceProject->id,
        'song_id' => $song->id,
        'storage_disk' => 'r2',
        'storage_path_pdf' => 'charts/shared/charted-song.pdf',
        'source_sha256' => hash('sha256', 'charted-song'),
        'original_filename' => 'charted-song.pdf',
        'has_renders' => true,
        'page_count' => 2,
    ]);
    ChartRender::factory()->create([
        'chart_id' => $sourceChart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Light,
        'storage_path_image' => 'charts/shared/charted-song/light/page-1.png',
    ]);
    ChartRender::factory()->create([
        'chart_id' => $sourceChart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Dark,
        'storage_path_image' => 'charts/shared/charted-song/dark/page-1.png',
    ]);
    $response = $this->postJson(
        "/api/v1/me/projects/{$this->destinationProject->id}/repertoire/copy-from",
        [
            'source_project_id' => $this->sourceProject->id,
            'source_project_song_ids' => [$projectSong->id],
            'include_charts' => true,
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('data.copied_songs', 1)
        ->assertJsonPath('data.copied_charts', 1);

    $copiedChart = Chart::query()
        ->where('project_id', $this->destinationProject->id)
        ->where('song_id', $song->id)
        ->first();

    expect($copiedChart)->not->toBeNull();
    expect($copiedChart?->id)->not->toBe($sourceChart->id);
    expect($copiedChart?->storage_path_pdf)->toBe($sourceChart->storage_path_pdf);

    $destinationProjectSong = ProjectSong::query()
        ->where('project_id', $this->destinationProject->id)
        ->where('song_id', $song->id)
        ->first();
    expect($copiedChart?->project_song_id)->toBe($destinationProjectSong?->id);
    expect(
        ChartRender::query()->where('chart_id', $copiedChart?->id)->count()
    )->toBe(2);
});

it('validates selected source songs belong to the source project', function () {
    Sanctum::actingAs($this->owner);

    $outsideProjectSong = ProjectSong::factory()->create([
        'project_id' => Project::factory()->create([
            'owner_user_id' => $this->owner->id,
        ])->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->destinationProject->id}/repertoire/copy-from",
        [
            'source_project_id' => $this->sourceProject->id,
            'source_project_song_ids' => [$outsideProjectSong->id],
            'include_charts' => false,
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['source_project_song_ids.0']);
});

it('copies saved annotations when include_annotations=true', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create([
        'title' => 'Annotated Song',
        'artist' => 'Annotated Artist',
    ]);
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $this->sourceProject->id,
        'user_id' => $this->owner->id,
        'song_id' => $song->id,
    ]);

    $sourceChart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->sourceProject->id,
        'song_id' => $song->id,
        'project_song_id' => $projectSong->id,
        'storage_disk' => 'r2',
        'storage_path_pdf' => 'charts/shared/annotated-song.pdf',
        'source_sha256' => hash('sha256', 'annotated-song'),
        'original_filename' => 'annotated-song.pdf',
        'has_renders' => true,
        'page_count' => 1,
    ]);
    ChartRender::factory()->create([
        'chart_id' => $sourceChart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Light,
        'storage_path_image' => 'charts/shared/annotated-song/light/page-1.png',
    ]);

    DB::table('chart_annotation_versions')->insert([
        'chart_id' => $sourceChart->id,
        'owner_user_id' => $this->owner->id,
        'page_number' => 1,
        'local_version_id' => 'copy-from-version-1',
        'strokes' => json_encode([
            [
                'points' => [['x' => 0.25, 'y' => 0.5]],
                'color_value' => 4281545523,
                'thickness' => 2.75,
                'is_eraser' => false,
            ],
        ]),
        'client_created_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->destinationProject->id}/repertoire/copy-from",
        [
            'source_project_id' => $this->sourceProject->id,
            'source_project_song_ids' => [$projectSong->id],
            'include_charts' => true,
            'include_annotations' => true,
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('data.copied_songs', 1)
        ->assertJsonPath('data.copied_charts', 1)
        ->assertJsonPath('data.copied_annotations', 1);

    $copiedChart = Chart::query()
        ->where('project_id', $this->destinationProject->id)
        ->where('song_id', $song->id)
        ->first();
    expect($copiedChart)->not->toBeNull();
    expect($copiedChart?->id)->not->toBe($sourceChart->id);

    $copiedAnnotation = ChartAnnotationVersion::query()
        ->where('chart_id', $copiedChart?->id)
        ->where('owner_user_id', $this->owner->id)
        ->where('page_number', 1)
        ->first();

    expect($copiedAnnotation)->not->toBeNull();
    expect($copiedAnnotation?->local_version_id)->toBe('copy-from-version-1');
    expect($copiedAnnotation?->strokes[0]['thickness'])->toBe(2.75);

    // Source annotation untouched.
    expect(
        ChartAnnotationVersion::query()
            ->where('chart_id', $sourceChart->id)
            ->count()
    )->toBe(1);
});

it('copies charts without annotations when include_annotations is omitted', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create([
        'title' => 'Omit-Annotations Song',
    ]);
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $this->sourceProject->id,
        'user_id' => $this->owner->id,
        'song_id' => $song->id,
    ]);

    $sourceChart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->sourceProject->id,
        'song_id' => $song->id,
        'project_song_id' => $projectSong->id,
        'storage_disk' => 'r2',
        'storage_path_pdf' => 'charts/shared/omit-annotations.pdf',
        'source_sha256' => hash('sha256', 'omit-annotations'),
        'original_filename' => 'omit-annotations.pdf',
        'has_renders' => false,
        'page_count' => 1,
    ]);

    DB::table('chart_annotation_versions')->insert([
        'chart_id' => $sourceChart->id,
        'owner_user_id' => $this->owner->id,
        'page_number' => 1,
        'local_version_id' => 'should-not-copy',
        'strokes' => json_encode([]),
        'client_created_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->destinationProject->id}/repertoire/copy-from",
        [
            'source_project_id' => $this->sourceProject->id,
            'source_project_song_ids' => [$projectSong->id],
            'include_charts' => true,
            // include_annotations omitted -> default false in CopyRepertoireRequest.
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('data.copied_charts', 1)
        ->assertJsonPath('data.copied_annotations', 0);

    $copiedChart = Chart::query()
        ->where('project_id', $this->destinationProject->id)
        ->where('song_id', $song->id)
        ->first();

    expect(
        ChartAnnotationVersion::query()->where('chart_id', $copiedChart?->id)->count()
    )->toBe(0);
});

it('silently drops include_annotations when include_charts=false in copy-from', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $this->sourceProject->id,
        'user_id' => $this->owner->id,
        'song_id' => $song->id,
    ]);
    $sourceChart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->sourceProject->id,
        'song_id' => $song->id,
        'project_song_id' => $projectSong->id,
        'storage_disk' => 'r2',
        'storage_path_pdf' => 'charts/shared/drop-ann.pdf',
        'source_sha256' => hash('sha256', 'drop-ann'),
        'original_filename' => 'drop-ann.pdf',
        'has_renders' => false,
        'page_count' => 1,
    ]);
    DB::table('chart_annotation_versions')->insert([
        'chart_id' => $sourceChart->id,
        'owner_user_id' => $this->owner->id,
        'page_number' => 1,
        'local_version_id' => 'drop-me',
        'strokes' => json_encode([]),
        'client_created_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->destinationProject->id}/repertoire/copy-from",
        [
            'source_project_id' => $this->sourceProject->id,
            'source_project_song_ids' => [$projectSong->id],
            'include_charts' => false,
            'include_annotations' => true,
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('data.copied_songs', 1)
        ->assertJsonPath('data.copied_charts', 0)
        ->assertJsonPath('data.copied_annotations', 0);
});
