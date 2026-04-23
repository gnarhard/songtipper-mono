<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use App\Services\BulkImportConfirmService;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
});

it('confirms a bulk import and returns summary', function () {
    Sanctum::actingAs($this->owner);

    $this->mock(BulkImportConfirmService::class, function (MockInterface $mock) {
        $mock->shouldReceive('confirm')
            ->once()
            ->andReturn([
                'imported' => 3,
                'duplicates' => 1,
                'limit_reached' => 0,
                'no_match' => 0,
                'songs' => [],
            ]);
    });

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-import/confirm",
        [
            'songs' => [
                ['title' => 'Song A', 'artist' => 'Artist A'],
                ['title' => 'Song B', 'artist' => 'Artist B'],
                ['title' => 'Song C', 'artist' => 'Artist C'],
                ['title' => 'Song D', 'artist' => 'Artist D'],
            ],
        ]
    );

    $response->assertOk()
        ->assertJsonPath('data.imported', 3)
        ->assertJsonPath('data.duplicates', 1);
});

it('creates a dedicated Song row for mashups during bulk import, bypassing catalog dedup', function () {
    Sanctum::actingAs($this->owner);

    $catalogSong = Song::factory()->create([
        'title' => 'Bulk Shared Title',
        'artist' => 'Bulk Shared Artist',
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-import/confirm",
        [
            'songs' => [
                [
                    'title' => 'Bulk Shared Title',
                    'artist' => 'Bulk Shared Artist',
                    'mashup' => true,
                ],
            ],
        ]
    );

    $response->assertOk()->assertJsonPath('data.imported', 1);

    $projectSong = ProjectSong::query()
        ->where('project_id', $this->project->id)
        ->where('mashup', true)
        ->firstOrFail();

    expect($projectSong->song_id)->not->toBe($catalogSong->id);
    expect(Song::query()->find($projectSong->song_id)->normalized_key)->toStartWith('mashup:');
});

it('returns 404 for projects the user cannot access', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/repertoire/bulk-import/confirm",
        ['songs' => [['title' => 'Test', 'artist' => 'Artist']]]
    );

    $response->assertNotFound();
});
