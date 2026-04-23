<?php

declare(strict_types=1);

use App\Enums\RequestStatus;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Request as SongRequest;
use App\Models\Song;
use App\Models\User;

it('returns true when marking an already-played request as played', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $song = Song::factory()->create();
    ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
    ]);

    $request = SongRequest::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'status' => RequestStatus::Played,
        'played_at' => now(),
    ]);

    // Calling markAsPlayed on an already-played request should return true
    // and sync attributes (covers line 130-132, the early return for already played)
    $result = $request->markAsPlayed();

    expect($result)->toBeTrue();
    expect($request->status)->toBe(RequestStatus::Played);
});
