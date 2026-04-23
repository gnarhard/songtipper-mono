<?php

declare(strict_types=1);

use App\Enums\SongTheme;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use Illuminate\Database\Migrations\Migration;

it('backfills legacy song and project song themes without chunkById query failures', function (): void {
    $song = Song::factory()->create([
        'normalized_key' => 'chunkbyid-regression',
        'theme' => 'legacy_song_theme',
    ]);

    $projectSong = ProjectSong::factory()->create([
        'project_id' => Project::factory()->create()->id,
        'song_id' => $song->id,
        'theme' => 'legacy_project_song_theme',
    ]);

    /** @var Migration $migration */
    $migration = require base_path('database/migrations/2026_02_23_035020_backfill_song_themes_to_canonical_song_theme_enum.php');
    $migration->up();

    expect($song->fresh()->theme)->toBe(SongTheme::Story->value);
    expect($projectSong->fresh()->theme)->toBe(SongTheme::Story->value);
});
