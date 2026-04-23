<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\ChartAnnotationVersion;
use App\Models\Project;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $this->song = Song::factory()->create();
    $this->chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
    ]);
});

it('stores a saved annotation for an owned chart page', function () {
    Sanctum::actingAs($this->owner);

    $payload = [
        'chart_id' => $this->chart->id,
        'page_number' => 1,
        'local_version_id' => 'local-version-1',
        'created_at' => '2026-02-11T20:15:00Z',
        'strokes' => [
            [
                'points' => [
                    ['x' => 0.1, 'y' => 0.2],
                    ['x' => 0.3, 'y' => 0.4],
                ],
                'color_value' => 4281545523,
                'thickness' => 2.5,
                'is_eraser' => false,
            ],
        ],
    ];

    $this->postJson("/api/v1/me/charts/{$this->chart->id}/pages/1/annotations", $payload)
        ->assertCreated()
        ->assertJsonPath('message', 'Annotation saved.')
        ->assertJsonPath('annotation.chart_id', $this->chart->id)
        ->assertJsonPath('annotation.page_number', 1)
        ->assertJsonPath('annotation.local_version_id', 'local-version-1');

    $this->assertDatabaseHas('chart_annotation_versions', [
        'chart_id' => $this->chart->id,
        'owner_user_id' => $this->owner->id,
        'page_number' => 1,
        'local_version_id' => 'local-version-1',
    ]);
});

it('replaces the existing annotation for the same chart page', function () {
    Sanctum::actingAs($this->owner);

    $firstPayload = [
        'local_version_id' => 'local-version-1',
        'created_at' => '2026-02-11T20:15:00Z',
        'strokes' => [
            [
                'points' => [
                    ['x' => 0.1, 'y' => 0.2],
                    ['x' => 0.2, 'y' => 0.3],
                ],
                'color_value' => 4281545523,
                'thickness' => 2.5,
                'is_eraser' => false,
            ],
        ],
    ];
    $secondPayload = [
        'local_version_id' => 'local-version-2',
        'created_at' => '2026-02-11T20:20:00Z',
        'strokes' => [],
    ];

    $this->postJson(
        "/api/v1/me/charts/{$this->chart->id}/pages/1/annotations",
        $firstPayload
    )
        ->assertCreated();

    $this->postJson(
        "/api/v1/me/charts/{$this->chart->id}/pages/1/annotations",
        $secondPayload
    )
        ->assertOk()
        ->assertJsonPath('annotation.local_version_id', 'local-version-2')
        ->assertJsonPath('annotation.strokes', []);

    expect(
        ChartAnnotationVersion::query()
            ->where('chart_id', $this->chart->id)
            ->where('owner_user_id', $this->owner->id)
            ->where('page_number', 1)
            ->count()
    )->toBe(1);
});

it('returns the saved annotation for the latest endpoint', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/me/charts/{$this->chart->id}/pages/1/annotations", [
        'local_version_id' => 'latest-version',
        'created_at' => '2026-02-11T20:15:00Z',
        'strokes' => [],
    ])->assertCreated();

    $this->getJson("/api/v1/me/charts/{$this->chart->id}/pages/1/annotations/latest")
        ->assertOk()
        ->assertJsonPath('data.local_version_id', 'latest-version')
        ->assertJsonPath('data.page_number', 1);
});

it('keeps the chart page index required by the chart foreign key', function () {
    $index = collect(Schema::getIndexes('chart_annotation_versions'))
        ->firstWhere('name', 'chart_annotations_chart_page_index');

    expect($index)->not()->toBeNull();
});

it('can resume the single-state migration after the legacy unique index was already dropped', function () {
    $migration = require base_path(
        'database/migrations/2026_03_06_181232_reset_chart_annotations_to_single_state.php'
    );

    $migration->down();

    DB::table('chart_annotation_versions')->insert([
        'chart_id' => $this->chart->id,
        'owner_user_id' => $this->owner->id,
        'page_number' => 1,
        'local_version_id' => 'legacy-version-1',
        'base_version_id' => null,
        'strokes' => json_encode([]),
        'client_created_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Schema::table('chart_annotation_versions', function (Blueprint $table): void {
        $table->dropUnique('chart_annotations_unique_local_version');
    });

    $migration->up();

    expect(Schema::hasColumn('chart_annotation_versions', 'base_version_id'))->toBeFalse();
    expect(DB::table('chart_annotation_versions')->count())->toBe(0);
    expect(
        collect(Schema::getIndexes('chart_annotation_versions'))
            ->pluck('name')
            ->all()
    )->toContain('chart_annotations_unique_page');
});

it('rejects annotation reads from another users chart', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $this->getJson("/api/v1/me/charts/{$this->chart->id}/pages/1/annotations/latest")
        ->assertForbidden();
});

it('rejects annotation stores with mismatched page_number', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/me/charts/{$this->chart->id}/pages/1/annotations", [
        'page_number' => 5,
        'local_version_id' => 'mismatch-page',
        'created_at' => '2026-02-11T20:15:00Z',
        'strokes' => [],
    ])->assertStatus(422);
});

it('rejects annotation writes to another users chart', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $this->postJson("/api/v1/me/charts/{$this->chart->id}/pages/1/annotations", [
        'local_version_id' => 'forbidden-version',
        'created_at' => '2026-02-11T20:15:00Z',
        'strokes' => [],
    ])->assertForbidden();
});

it('requires authentication', function () {
    $this->postJson("/api/v1/me/charts/{$this->chart->id}/pages/1/annotations", [
        'local_version_id' => 'unauth-version',
        'created_at' => '2026-02-11T20:15:00Z',
        'strokes' => [],
    ])->assertUnauthorized();
});

it('rejects route and payload mismatches', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/me/charts/{$this->chart->id}/pages/2/annotations", [
        'chart_id' => $this->chart->id + 1,
        'page_number' => 1,
        'local_version_id' => 'mismatch-version',
        'created_at' => '2026-02-11T20:15:00Z',
        'strokes' => [],
    ])->assertStatus(422);
});

it('rejects strokes arrays larger than the per-request cap', function () {
    Sanctum::actingAs($this->owner);

    $strokeTemplate = [
        'points' => [['x' => 0.1, 'y' => 0.2]],
        'color_value' => 4281545523,
        'thickness' => 2.5,
        'is_eraser' => false,
    ];

    $this->postJson("/api/v1/me/charts/{$this->chart->id}/pages/1/annotations", [
        'local_version_id' => 'overflow-version',
        'created_at' => '2026-02-11T20:15:00Z',
        'strokes' => array_fill(0, 10001, $strokeTemplate),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['strokes']);
});
