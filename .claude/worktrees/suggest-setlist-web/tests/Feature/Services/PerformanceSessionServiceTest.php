<?php

declare(strict_types=1);

use App\Enums\PerformanceSessionMode;
use App\Models\PerformanceSession;
use App\Models\PerformanceSessionItem;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistSong;
use App\Models\Song;
use App\Services\PerformanceSessionService;

it('starts a manual performance session', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);
    $setlist = Setlist::factory()->create(['project_id' => $project->id]);
    $set = SetlistSet::factory()->create(['setlist_id' => $setlist->id, 'order_index' => 0]);
    $song = Song::factory()->create();
    $ps = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);
    SetlistSong::factory()->create(['set_id' => $set->id, 'project_song_id' => $ps->id, 'order_index' => 0]);

    $service = app(PerformanceSessionService::class);
    $session = $service->start($project, $setlist, PerformanceSessionMode::Manual);

    expect($session->is_active)->toBeTrue()
        ->and($session->mode)->toBe(PerformanceSessionMode::Manual)
        ->and($session->items)->toHaveCount(1);
});

it('starts a smart performance session', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);
    $setlist = Setlist::factory()->create(['project_id' => $project->id]);
    $set = SetlistSet::factory()->create(['setlist_id' => $setlist->id, 'order_index' => 0]);
    $song = Song::factory()->create();
    $ps = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);
    SetlistSong::factory()->create(['set_id' => $set->id, 'project_song_id' => $ps->id, 'order_index' => 0]);

    $service = app(PerformanceSessionService::class);
    $session = $service->start($project, $setlist, PerformanceSessionMode::Smart, 42);

    expect($session->mode)->toBe(PerformanceSessionMode::Smart)
        ->and($session->generation_version)->toBe('smart-v1')
        ->and($session->seed)->toBe(42);
});

it('throws when setlist does not belong to project', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $setlist = Setlist::factory()->create(['project_id' => $otherProject->id]);

    $service = app(PerformanceSessionService::class);
    $service->start($project, $setlist, PerformanceSessionMode::Manual);
})->throws(DomainException::class, 'Setlist does not belong to this project.');

it('throws when active session already exists', function () {
    $project = Project::factory()->create();
    $setlist = Setlist::factory()->create(['project_id' => $project->id]);

    PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'started_at' => now(),
    ]);

    $service = app(PerformanceSessionService::class);
    $service->start($project, $setlist, PerformanceSessionMode::Manual);
})->throws(DomainException::class, 'An active performance session already exists');

it('stops an active session', function () {
    $project = Project::factory()->create();
    PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'started_at' => now(),
    ]);

    $service = app(PerformanceSessionService::class);
    $result = $service->stop($project);

    expect($result)->not->toBeNull()
        ->and($result->is_active)->toBeFalse()
        ->and($result->ended_at)->not->toBeNull();
});

it('returns null when stopping with no active session', function () {
    $project = Project::factory()->create();

    $service = app(PerformanceSessionService::class);
    $result = $service->stop($project);

    expect($result)->toBeNull();
});

it('returns current active session', function () {
    $project = Project::factory()->create();
    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'started_at' => now(),
    ]);

    $service = app(PerformanceSessionService::class);
    $result = $service->current($project);

    expect($result->id)->toBe($session->id);
});

it('returns null when no current session', function () {
    $project = Project::factory()->create();

    $service = app(PerformanceSessionService::class);
    $result = $service->current($project);

    expect($result)->toBeNull();
});

it('completes a setlist item', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);
    $setlist = Setlist::factory()->create(['project_id' => $project->id]);
    $set = SetlistSet::factory()->create(['setlist_id' => $setlist->id]);
    $song = Song::factory()->create();
    $ps = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);
    $setlistSong = SetlistSong::factory()->create(['set_id' => $set->id, 'project_song_id' => $ps->id]);

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'setlist_id' => $setlist->id,
        'is_active' => true,
        'mode' => 'manual',
        'started_at' => now(),
    ]);

    PerformanceSessionItem::factory()->create([
        'performance_session_id' => $session->id,
        'setlist_set_id' => $set->id,
        'setlist_song_id' => $setlistSong->id,
        'project_song_id' => $ps->id,
        'status' => 'pending',
        'order_index' => 0,
    ]);

    $service = app(PerformanceSessionService::class);
    $result = $service->complete($session, $ps->id, 'setlist', $setlistSong->id, $user);

    expect($result)->not->toBeNull()
        ->and($result->status->value)->toBe('performed');

    $ps->refresh();
    expect($ps->performance_count)->toBe(1);
});

it('completes a repertoire item', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);
    $setlist = Setlist::factory()->create(['project_id' => $project->id]);

    $song = Song::factory()->create();
    $ps = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'setlist_id' => $setlist->id,
        'is_active' => true,
        'mode' => 'manual',
        'started_at' => now(),
    ]);

    $service = app(PerformanceSessionService::class);
    $result = $service->complete($session, $ps->id, 'repertoire', null, $user);

    // No existing item for repertoire source, but a SongPerformance is created
    expect($result)->toBeNull();

    $ps->refresh();
    expect($ps->performance_count)->toBe(1);
});

it('does not re-complete already performed item', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);
    $setlist = Setlist::factory()->create(['project_id' => $project->id]);
    $set = SetlistSet::factory()->create(['setlist_id' => $setlist->id]);
    $song = Song::factory()->create();
    $ps = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);
    $setlistSong = SetlistSong::factory()->create(['set_id' => $set->id, 'project_song_id' => $ps->id]);

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'setlist_id' => $setlist->id,
        'is_active' => true,
        'mode' => 'manual',
        'started_at' => now(),
    ]);

    $item = PerformanceSessionItem::factory()->create([
        'performance_session_id' => $session->id,
        'setlist_set_id' => $set->id,
        'setlist_song_id' => $setlistSong->id,
        'project_song_id' => $ps->id,
        'status' => 'performed',
        'performed_order_index' => 0,
        'performed_at' => now(),
        'order_index' => 0,
    ]);

    $service = app(PerformanceSessionService::class);
    $result = $service->complete($session, $ps->id, 'setlist', $setlistSong->id, $user);

    expect($result->id)->toBe($item->id);
});

it('skips an item', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();
    $ps = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'mode' => 'manual',
        'started_at' => now(),
    ]);

    PerformanceSessionItem::factory()->create([
        'performance_session_id' => $session->id,
        'project_song_id' => $ps->id,
        'status' => 'pending',
        'order_index' => 0,
    ]);

    $service = app(PerformanceSessionService::class);
    $result = $service->skip($session, $ps->id);

    expect($result)->not->toBeNull()
        ->and($result->status->value)->toBe('skipped');
});

it('returns null when skipping non-existent item', function () {
    $project = Project::factory()->create();

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'mode' => 'manual',
        'started_at' => now(),
    ]);

    $service = app(PerformanceSessionService::class);
    $result = $service->skip($session, 99999);

    expect($result)->toBeNull();
});

it('does not re-skip already skipped item', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();
    $ps = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'mode' => 'manual',
        'started_at' => now(),
    ]);

    PerformanceSessionItem::factory()->create([
        'performance_session_id' => $session->id,
        'project_song_id' => $ps->id,
        'status' => 'skipped',
        'skipped_at' => now(),
        'order_index' => 0,
    ]);

    $service = app(PerformanceSessionService::class);
    $result = $service->skip($session, $ps->id);

    expect($result)->not->toBeNull()
        ->and($result->status->value)->toBe('skipped');
});

it('returns random recommendation from pending items', function () {
    $project = Project::factory()->create();

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'mode' => 'smart',
        'seed' => 42,
        'started_at' => now(),
    ]);

    foreach (range(1, 3) as $i) {
        $song = Song::factory()->create();
        $ps = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song->id]);
        PerformanceSessionItem::factory()->create([
            'performance_session_id' => $session->id,
            'project_song_id' => $ps->id,
            'status' => 'pending',
            'order_index' => $i - 1,
        ]);
    }

    $service = app(PerformanceSessionService::class);
    $result = $service->randomRecommendation($session);

    expect($result)->not->toBeNull()
        ->and($result->status->value)->toBe('pending');
});

it('returns null recommendation when no pending items', function () {
    $project = Project::factory()->create();

    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'mode' => 'smart',
        'started_at' => now(),
    ]);

    $service = app(PerformanceSessionService::class);
    $result = $service->randomRecommendation($session);

    expect($result)->toBeNull();
});
