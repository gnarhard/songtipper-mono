<?php

declare(strict_types=1);

use App\Enums\EnergyLevel;
use App\Enums\RequestStatus;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Request;
use App\Models\Song;

it('returns highest active tip for a project song', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();

    $projectSong = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
    ]);

    Request::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'status' => RequestStatus::Active,
        'tip_amount_cents' => 1500,
    ]);

    expect($projectSong->highest_active_tip)->toBe(1500);
});

it('resolves energy level from project song when set', function () {
    $song = Song::factory()->create(['energy_level' => EnergyLevel::High]);
    $projectSong = ProjectSong::factory()->create([
        'song_id' => $song->id,
        'energy_level' => EnergyLevel::Low,
    ]);

    expect($projectSong->resolvedEnergyLevel())->toBe(EnergyLevel::Low);
});

it('resolves energy level from song when project song has none', function () {
    $song = Song::factory()->create(['energy_level' => EnergyLevel::High]);
    $projectSong = ProjectSong::factory()->create([
        'song_id' => $song->id,
        'energy_level' => null,
    ]);
    $projectSong->load('song');

    expect($projectSong->resolvedEnergyLevel())->toBe(EnergyLevel::High);
});

it('resolves genre from project song when set', function () {
    $song = Song::factory()->create(['genre' => 'Jazz']);
    $projectSong = ProjectSong::factory()->create([
        'song_id' => $song->id,
        'genre' => 'Rock',
    ]);

    expect($projectSong->resolvedGenre())->toBe('Rock');
});

it('resolves genre from song when project song has none', function () {
    $song = Song::factory()->create(['genre' => 'Jazz']);
    $projectSong = ProjectSong::factory()->create([
        'song_id' => $song->id,
        'genre' => null,
    ]);
    $projectSong->load('song');

    expect($projectSong->resolvedGenre())->toBe('Jazz');
});

it('resolves theme from project song when set', function () {
    $song = Song::factory()->create(['theme' => 'love']);
    $projectSong = ProjectSong::factory()->create([
        'song_id' => $song->id,
        'theme' => 'party',
    ]);

    expect($projectSong->resolvedTheme())->toBe('party');
});

it('resolves theme from song when project song has none', function () {
    $song = Song::factory()->create(['theme' => 'love']);
    $projectSong = ProjectSong::factory()->create([
        'song_id' => $song->id,
        'theme' => null,
    ]);
    $projectSong->load('song');

    expect($projectSong->resolvedTheme())->toBe('love');
});
