<?php

declare(strict_types=1);

use App\Enums\SongTheme;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use Database\Seeders\GraysonErhardProjectSongsSeeder;
use Illuminate\Support\Facades\Storage;

it('imports all legacy songs into the grayson-erhard project with stats', function () {
    $this->seed(GraysonErhardProjectSongsSeeder::class);

    $project = Project::query()->where('slug', 'grayson-erhard')->first();

    expect($project)->not->toBeNull();
    expect($project->performer_profile_image_path)->toBe('images/grayson_erhard_profile.jpg');
    expect(Storage::disk('public')->exists('images/grayson_erhard_profile.jpg'))->toBeTrue();
    expect(Song::query()->count())->toBe(148);
    expect(ProjectSong::query()->where('project_id', $project->id)->count())->toBe(148);

    $loveSong = Song::query()->where('title', 'Love Song')->where('artist', '311')->first();
    expect($loveSong)->not->toBeNull();
    expect($loveSong->original_musical_key?->value)->toBe('Am');
    expect($loveSong->genre)->toBe('Reggae');
    expect($loveSong->energy_level?->value)->toBe('low');

    $projectSong = ProjectSong::query()
        ->where('project_id', $project->id)
        ->where('song_id', $loveSong->id)
        ->first();

    expect($projectSong)->not->toBeNull();
    expect($projectSong->energy_level?->value)->toBe('low');
    expect($projectSong->genre)->toBe('Reggae');
    expect($projectSong->performed_musical_key?->value)->toBe('Am');
    expect($projectSong->performance_count)->toBe(2);
    expect($projectSong->needs_improvement)->toBeFalse();
    expect($projectSong->last_performed_at?->toDateTimeString())->toBe('2025-11-15 22:48:09');

    $allowedThemes = SongTheme::values();

    expect(
        Song::query()
            ->whereNotNull('theme')
            ->whereNotIn('theme', $allowedThemes)
            ->count()
    )->toBe(0);

    expect(
        ProjectSong::query()
            ->whereNotNull('theme')
            ->whereNotIn('theme', $allowedThemes)
            ->count()
    )->toBe(0);
});

it('is idempotent when seeded multiple times', function () {
    $this->seed(GraysonErhardProjectSongsSeeder::class);
    $this->seed(GraysonErhardProjectSongsSeeder::class);

    $project = Project::query()->where('slug', 'grayson-erhard')->firstOrFail();

    expect(Song::query()->count())->toBe(148);
    expect(ProjectSong::query()->where('project_id', $project->id)->count())->toBe(148);
});
