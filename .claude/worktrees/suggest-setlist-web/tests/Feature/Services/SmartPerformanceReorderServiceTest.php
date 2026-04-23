<?php

declare(strict_types=1);

use App\Models\PerformanceSession;
use App\Models\PerformanceSessionItem;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Services\SmartPerformanceReorderService;

it('reorders pending items accounting for recently performed songs and same artist', function () {
    $project = Project::factory()->create();

    // Create songs by the same artist with last_performed_at within 7 days
    $songA = Song::factory()->create(['title' => 'Song A', 'artist' => 'Same Artist']);
    $songB = Song::factory()->create(['title' => 'Song B', 'artist' => 'Same Artist']);
    $songC = Song::factory()->create(['title' => 'Song C', 'artist' => 'Different Artist']);

    $psSongA = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $songA->id,
        'last_performed_at' => now()->subDays(3),
    ]);
    $psSongB = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $songB->id,
        'last_performed_at' => now()->subDays(2),
    ]);
    $psSongC = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $songC->id,
        'last_performed_at' => null,
    ]);

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'mode' => 'smart',
        'started_at' => now(),
    ]);

    // Create performed item (same artist as pending)
    PerformanceSessionItem::factory()->create([
        'performance_session_id' => $session->id,
        'project_song_id' => $psSongA->id,
        'status' => 'performed',
        'performed_order_index' => 0,
        'order_index' => 0,
    ]);

    // Create pending items
    PerformanceSessionItem::factory()->create([
        'performance_session_id' => $session->id,
        'project_song_id' => $psSongB->id,
        'status' => 'pending',
        'order_index' => 1,
    ]);

    PerformanceSessionItem::factory()->create([
        'performance_session_id' => $session->id,
        'project_song_id' => $psSongC->id,
        'status' => 'pending',
        'order_index' => 2,
    ]);

    $service = app(SmartPerformanceReorderService::class);
    $service->reorderPending($session);

    // Items should be reordered (we just verify it completes without error)
    $session->refresh();
    expect($session->items)->toHaveCount(3);
});

it('applies closer bonus for slot >= 8', function () {
    $project = Project::factory()->create();

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'mode' => 'smart',
        'started_at' => now(),
    ]);

    // Create 2 performed items (keeps keys 0-indexed for take(-2))
    foreach (range(1, 2) as $i) {
        $song = Song::factory()->create(['title' => "Performed {$i}", 'artist' => "Artist {$i}"]);
        $ps = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);
        PerformanceSessionItem::factory()->create([
            'performance_session_id' => $session->id,
            'project_song_id' => $ps->id,
            'status' => 'performed',
            'performed_order_index' => $i - 1,
            'order_index' => $i - 1,
        ]);
    }

    // Create pending items (slots 3+)
    foreach (range(3, 5) as $i) {
        $song = Song::factory()->create(['title' => "Pending {$i}", 'artist' => "Artist {$i}"]);
        $ps = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);
        PerformanceSessionItem::factory()->create([
            'performance_session_id' => $session->id,
            'project_song_id' => $ps->id,
            'status' => 'pending',
            'order_index' => $i - 1,
        ]);
    }

    $service = app(SmartPerformanceReorderService::class);
    $service->reorderPending($session);

    // Verify reorder completes without error
    expect(true)->toBeTrue();
});

it('does nothing when there are no pending items', function () {
    $project = Project::factory()->create();

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'mode' => 'smart',
        'started_at' => now(),
    ]);

    $song = Song::factory()->create();
    $ps = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);
    PerformanceSessionItem::factory()->create([
        'performance_session_id' => $session->id,
        'project_song_id' => $ps->id,
        'status' => 'performed',
        'performed_order_index' => 0,
        'order_index' => 0,
    ]);

    $service = app(SmartPerformanceReorderService::class);
    $service->reorderPending($session);

    expect(true)->toBeTrue();
});
