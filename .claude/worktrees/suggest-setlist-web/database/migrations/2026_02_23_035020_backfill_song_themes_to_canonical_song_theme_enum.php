<?php

declare(strict_types=1);

use App\Enums\SongTheme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    public function up(): void
    {
        $canonicalThemes = SongTheme::values();
        $defaultFallback = SongTheme::Story->value;
        $map = $this->loadBackfillMap($defaultFallback, $canonicalThemes);
        $fallbackTheme = $map['fallback'];
        $themesByNormalizedKey = $map['themes_by_normalized_key'];

        DB::table('songs')
            ->select(['id', 'normalized_key', 'theme'])
            ->orderBy('id')
            ->chunkById(500, function ($songs) use ($canonicalThemes, $fallbackTheme, $themesByNormalizedKey): void {
                foreach ($songs as $song) {
                    $mappedTheme = $themesByNormalizedKey[$song->normalized_key] ?? null;
                    $targetTheme = $this->resolveTargetTheme(
                        currentTheme: $song->theme,
                        mappedTheme: $mappedTheme,
                        fallbackTheme: $fallbackTheme,
                        canonicalThemes: $canonicalThemes,
                    );

                    if ($targetTheme === $song->theme) {
                        continue;
                    }

                    DB::table('songs')
                        ->where('id', $song->id)
                        ->update(['theme' => $targetTheme]);
                }
            });

        DB::table('project_songs')
            ->join('songs', 'songs.id', '=', 'project_songs.song_id')
            ->select([
                'project_songs.id as id',
                'project_songs.theme as theme',
                'songs.normalized_key as normalized_key',
            ])
            ->orderBy('project_songs.id')
            ->chunkById(
                500,
                function ($projectSongs) use ($canonicalThemes, $fallbackTheme, $themesByNormalizedKey): void {
                    foreach ($projectSongs as $projectSong) {
                        $mappedTheme = $themesByNormalizedKey[$projectSong->normalized_key] ?? null;
                        $targetTheme = $this->resolveTargetTheme(
                            currentTheme: $projectSong->theme,
                            mappedTheme: $mappedTheme,
                            fallbackTheme: $fallbackTheme,
                            canonicalThemes: $canonicalThemes,
                        );

                        if ($targetTheme === $projectSong->theme) {
                            continue;
                        }

                        DB::table('project_songs')
                            ->where('id', $projectSong->id)
                            ->update(['theme' => $targetTheme]);
                    }
                },
                'project_songs.id',
                'id',
            );

        DB::table('songs')
            ->whereNotNull('theme')
            ->whereNotIn('theme', $canonicalThemes)
            ->update(['theme' => $fallbackTheme]);

        DB::table('project_songs')
            ->whereNotNull('theme')
            ->whereNotIn('theme', $canonicalThemes)
            ->update(['theme' => $fallbackTheme]);
    }

    /**
     * @param  array<int, string>  $canonicalThemes
     */
    private function resolveTargetTheme(
        mixed $currentTheme,
        ?string $mappedTheme,
        string $fallbackTheme,
        array $canonicalThemes
    ): ?string {
        if (is_string($mappedTheme) && in_array($mappedTheme, $canonicalThemes, true)) {
            return $mappedTheme;
        }

        if (! is_string($currentTheme)) {
            return null;
        }

        $trimmedCurrent = trim($currentTheme);
        if ($trimmedCurrent === '') {
            return null;
        }

        if (in_array($trimmedCurrent, $canonicalThemes, true)) {
            return $trimmedCurrent;
        }

        return $fallbackTheme;
    }

    /**
     * @param  array<int, string>  $canonicalThemes
     * @return array{
     *   fallback: string,
     *   themes_by_normalized_key: array<string, string>
     * }
     */
    private function loadBackfillMap(
        string $defaultFallback,
        array $canonicalThemes
    ): array {
        $path = database_path('data/song_theme_backfill_map.php');

        if (! File::exists($path)) {
            return [
                'fallback' => $defaultFallback,
                'themes_by_normalized_key' => [],
            ];
        }

        $rawMap = require $path;
        if (! is_array($rawMap)) {
            return [
                'fallback' => $defaultFallback,
                'themes_by_normalized_key' => [],
            ];
        }

        $fallback = is_string($rawMap['fallback'] ?? null) && in_array($rawMap['fallback'], $canonicalThemes, true)
            ? $rawMap['fallback']
            : $defaultFallback;

        $themesByNormalizedKey = [];
        $rawThemesByNormalizedKey = $rawMap['themes_by_normalized_key'] ?? [];
        if (is_array($rawThemesByNormalizedKey)) {
            foreach ($rawThemesByNormalizedKey as $normalizedKey => $theme) {
                if (
                    is_string($normalizedKey) &&
                    is_string($theme) &&
                    in_array($theme, $canonicalThemes, true)
                ) {
                    $themesByNormalizedKey[$normalizedKey] = $theme;
                }
            }
        }

        return [
            'fallback' => $fallback,
            'themes_by_normalized_key' => $themesByNormalizedKey,
        ];
    }

    public function down(): void
    {
        // This migration is intentionally irreversible because it replaces invalid legacy values.
    }
};
