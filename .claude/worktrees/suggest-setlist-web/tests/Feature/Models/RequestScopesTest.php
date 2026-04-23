<?php

declare(strict_types=1);

use App\Enums\RequestStatus;
use App\Models\Project;
use App\Models\Request;
use App\Models\Song;

it('scopes active requests', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();

    Request::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'status' => RequestStatus::Active,
    ]);
    Request::factory()->played()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
    ]);

    $active = Request::query()->active()->get();

    expect($active)->toHaveCount(1);
    expect($active->first()->status)->toBe(RequestStatus::Active);
});

it('scopes played requests', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();

    Request::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'status' => RequestStatus::Active,
    ]);
    Request::factory()->played()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
    ]);

    $played = Request::query()->played()->get();

    expect($played)->toHaveCount(1);
    expect($played->first()->status)->toBe(RequestStatus::Played);
});

it('marks request as played', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();

    $request = Request::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'status' => RequestStatus::Active,
    ]);

    $result = $request->markAsPlayed();

    expect($result)->toBeTrue();
    expect($request->status)->toBe(RequestStatus::Played);
    expect($request->played_at)->not->toBeNull();
});
