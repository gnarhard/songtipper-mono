<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Era;
use App\Enums\Genre;
use App\Models\Song;
use Illuminate\Database\Seeder;
use RuntimeException;

class PopularCoverSongsSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/top_cover_library_songs.json');
        if (! is_file($path)) {
            throw new RuntimeException("Missing seed data file: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload)) {
            throw new RuntimeException('Invalid top cover library JSON payload.');
        }

        $songs = $payload['songs'] ?? null;
        if (! is_array($songs)) {
            throw new RuntimeException('Missing songs list in top cover library payload.');
        }

        foreach ($songs as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $title = trim((string) ($entry['title'] ?? ''));
            $artist = trim((string) ($entry['artist'] ?? ''));

            if ($title === '' || $artist === '') {
                continue;
            }

            $normalizedKey = Song::generateNormalizedKey($title, $artist);
            Song::query()->updateOrCreate(
                ['normalized_key' => $normalizedKey],
                [
                    'title' => $title,
                    'artist' => $artist,
                    'energy_level' => $this->normalizeEnergyLevel($entry['energy_level'] ?? null),
                    'era' => $this->normalizeEra($entry['era'] ?? null),
                    'genre' => $this->normalizeGenre($entry['genre'] ?? null),
                    'original_musical_key' => $this->normalizeKey($entry['original_musical_key'] ?? null),
                    'duration_in_seconds' => $this->normalizeDuration($entry['duration_in_seconds'] ?? null),
                ]
            );
        }
    }

    private function normalizeEnergyLevel(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if (! in_array($normalized, ['low', 'medium', 'high'], true)) {
            return null;
        }

        return $normalized;
    }

    private function normalizeEra(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return Era::normalize($value);
    }

    private function normalizeGenre(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return Genre::normalize($value);
    }

    private function normalizeKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, 8);
    }

    private function normalizeDuration(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $duration = (int) $value;
        if ($duration < 0 || $duration > 86400) {
            return null;
        }

        return $duration;
    }
}
