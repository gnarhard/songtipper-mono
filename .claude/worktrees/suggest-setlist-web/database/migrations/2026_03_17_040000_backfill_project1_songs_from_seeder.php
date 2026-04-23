<?php

declare(strict_types=1);

use App\Jobs\EnrichImportedSongs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PROJECT_ID = 1;

    private const SEED_DATA_PATH = 'seeders/data/grayson_erhard_project_songs.json';

    public function up(): void
    {
        if (! DB::table('projects')->where('id', self::PROJECT_ID)->exists()) {
            return;
        }

        $payload = $this->readPayload();
        $songs = $payload['songs'] ?? null;

        if (! is_array($songs)) {
            return;
        }

        $now = now()->toDateTimeString();
        $newSongIds = [];

        DB::transaction(function () use ($songs, $now, &$newSongIds): void {
            foreach ($songs as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $title = trim((string) ($entry['title'] ?? ''));
                $artist = trim((string) ($entry['artist'] ?? ''));
                if ($title === '' || $artist === '') {
                    continue;
                }

                $normalizedKey = $this->generateNormalizedKey($title, $artist);

                $song = DB::table('songs')->where('normalized_key', $normalizedKey)->first();

                if (! $song) {
                    $songId = DB::table('songs')->insertGetId([
                        'title' => $title,
                        'artist' => $artist,
                        'normalized_key' => $normalizedKey,
                        'energy_level' => $this->normalizeEnergyLevel($entry['energy_level'] ?? null),
                        'genre' => $this->truncateNullableString($entry['genre'] ?? null, 50),
                        'era' => $this->truncateNullableString($entry['era'] ?? null, 50),
                        'theme' => $this->normalizeTheme($entry['theme'] ?? null),
                        'original_musical_key' => $this->normalizeKey($entry['original_musical_key'] ?? null),
                        'duration_in_seconds' => $this->normalizeDuration($entry['duration_in_seconds'] ?? null),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $newSongIds[] = $songId;
                } else {
                    $songId = $song->id;
                }

                $isPublic = ($entry['is_public'] ?? true) ? 1 : 0;
                $performanceCount = max(0, (int) ($entry['performance_count'] ?? 0));
                $lastPerformedAt = $this->normalizeTimestamp($entry['last_performed_at'] ?? null);

                $existing = DB::table('project_songs')
                    ->where('project_id', self::PROJECT_ID)
                    ->where('song_id', $songId)
                    ->first();

                if ($existing) {
                    DB::table('project_songs')
                        ->where('id', $existing->id)
                        ->update([
                            'performance_count' => $performanceCount,
                            'last_performed_at' => $lastPerformedAt,
                            'is_public' => $isPublic,
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('project_songs')->insert([
                        'project_id' => self::PROJECT_ID,
                        'song_id' => $songId,
                        'version_label' => '',
                        'energy_level' => $this->normalizeEnergyLevel($entry['energy_level'] ?? null),
                        'genre' => $this->truncateNullableString($entry['genre'] ?? null, 50),
                        'theme' => $this->normalizeTheme($entry['theme'] ?? null),
                        'needs_improvement' => (bool) ($entry['needs_improvement'] ?? false),
                        'performance_count' => $performanceCount,
                        'last_performed_at' => $lastPerformedAt,
                        'is_public' => $isPublic,
                        'instrumental' => 0,
                        'mashup' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });

        if ($newSongIds !== []) {
            EnrichImportedSongs::dispatch($newSongIds, self::PROJECT_ID);
        }
    }

    public function down(): void
    {
        // Not reversible — performance data cannot be un-backfilled
    }

    private function generateNormalizedKey(string $title, string $artist): string
    {
        $normalize = fn (string $str): string => Str::of($str)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]/', '')
            ->toString();

        return $normalize($title).'|'.$normalize($artist);
    }

    /**
     * @return array<string, mixed>
     */
    private function readPayload(): array
    {
        $path = database_path(self::SEED_DATA_PATH);
        if (! is_file($path)) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) ? $payload : [];
    }

    private function normalizeEnergyLevel(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, ['low', 'medium', 'high'], true) ? $normalized : null;
    }

    private function normalizeKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '' || ! preg_match('/^[A-G](?:#|b)?m?$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    private function normalizeDuration(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $duration = (int) $value;

        return ($duration >= 0 && $duration <= 86400) ? $duration : null;
    }

    private function normalizeTheme(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : strtolower($trimmed);
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function truncateNullableString(mixed $value, int $length): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : mb_substr($normalized, 0, $length);
    }
};
