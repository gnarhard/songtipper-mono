<?php

declare(strict_types=1);

use App\Models\PerformanceSession;
use App\Models\PerformanceSessionItem;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Services\SmartSetlistHistoryService;

it('skips sessions with no performed items that have a project song', function () {
    $project = Project::factory()->create();

    // Create a completed session with items that have 'pending' status (not performed)
    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'ended_at' => now(),
        'started_at' => now()->subHour(),
    ]);

    $projectSong = ProjectSong::factory()->create(['project_id' => $project->id]);

    // Create a performed item but delete its projectSong so the filter returns empty
    $item = PerformanceSessionItem::factory()->create([
        'performance_session_id' => $session->id,
        'project_song_id' => $projectSong->id,
        'status' => 'performed',
        'performed_order_index' => 0,
    ]);

    // Delete the project song so the relationship returns null
    $projectSong->forceDelete();

    $service = new SmartSetlistHistoryService;
    $features = $service->buildProjectFeatures($project->id);

    expect($features['transition_song'])->toBe([])
        ->and($features['opener_counts'])->toBe([])
        ->and($features['closer_counts'])->toBe([]);
});

it('builds features from sessions with valid performed items', function () {
    $project = Project::factory()->create();
    $projectSongA = ProjectSong::factory()->create(['project_id' => $project->id]);
    $projectSongB = ProjectSong::factory()->create(['project_id' => $project->id]);

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'ended_at' => now(),
        'started_at' => now()->subDay(),
    ]);

    PerformanceSessionItem::factory()->create([
        'performance_session_id' => $session->id,
        'project_song_id' => $projectSongA->id,
        'status' => 'performed',
        'performed_order_index' => 0,
    ]);

    PerformanceSessionItem::factory()->create([
        'performance_session_id' => $session->id,
        'project_song_id' => $projectSongB->id,
        'status' => 'performed',
        'performed_order_index' => 1,
    ]);

    $service = new SmartSetlistHistoryService;
    $features = $service->buildProjectFeatures($project->id);

    expect($features['opener_counts'])->toHaveKey($projectSongA->id)
        ->and($features['closer_counts'])->toHaveKey($projectSongB->id)
        ->and($features['transition_song'])->not->toBe([]);
});
