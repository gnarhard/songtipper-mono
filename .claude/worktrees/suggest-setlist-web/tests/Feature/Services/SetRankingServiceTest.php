<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Services\SetRankingService;

it('ranks by performance count when revenue and popularity are tied', function () {
    $project = Project::factory()->create();

    $songA = Song::factory()->create(['title' => 'Alpha', 'artist' => 'Same']);
    $songB = Song::factory()->create(['title' => 'Beta', 'artist' => 'Same']);

    ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $songA->id,
        'performance_count' => 5,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $songB->id,
        'performance_count' => 10,
    ]);

    $service = new SetRankingService;
    $ranked = $service->rankedCandidates($project);

    // Song B has higher performance_count, should come first
    expect($ranked->first()->song_id)->toBe($songB->id);
});

it('ranks by title when revenue, popularity, and performance count are tied', function () {
    $project = Project::factory()->create();

    $songAlpha = Song::factory()->create(['title' => 'Alpha Song', 'artist' => 'Same']);
    $songBeta = Song::factory()->create(['title' => 'Beta Song', 'artist' => 'Same']);

    ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $songAlpha->id,
        'performance_count' => 0,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $songBeta->id,
        'performance_count' => 0,
    ]);

    $service = new SetRankingService;
    $ranked = $service->rankedCandidates($project);

    // Alpha comes first alphabetically
    expect($ranked->first()->song_id)->toBe($songAlpha->id);
});

it('ranks by artist when title is also tied', function () {
    $project = Project::factory()->create();

    $songA = Song::factory()->create(['title' => 'Same Title', 'artist' => 'Alpha Artist']);
    $songB = Song::factory()->create(['title' => 'Same Title', 'artist' => 'Beta Artist']);

    ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $songA->id,
        'performance_count' => 0,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $songB->id,
        'performance_count' => 0,
    ]);

    $service = new SetRankingService;
    $ranked = $service->rankedCandidates($project);

    // Alpha Artist comes first
    expect($ranked->first()->song_id)->toBe($songA->id);
});

it('excludes specified project song ids', function () {
    $project = Project::factory()->create();

    $song1 = Song::factory()->create();
    $song2 = Song::factory()->create();
    $ps1 = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song1->id]);
    $ps2 = ProjectSong::factory()->create(['project_id' => $project->id, 'song_id' => $song2->id]);

    $service = new SetRankingService;
    $ranked = $service->rankedCandidates($project, [$ps1->id]);

    expect($ranked)->toHaveCount(1)
        ->and($ranked->first()->id)->toBe($ps2->id);
});
