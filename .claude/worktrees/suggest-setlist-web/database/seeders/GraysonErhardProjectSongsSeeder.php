<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SongTheme;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GraysonErhardProjectSongsSeeder extends Seeder
{
    private const PROJECT_SLUG = 'grayson-erhard';

    private const OWNER_EMAIL = 'grayson@example.com';

    private const OWNER_NAME = 'Grayson Erhard';

    private const SEED_DATA_PATH = 'seeders/data/grayson_erhard_project_songs.json';

    private const PROFILE_IMAGE_SOURCE_PATH = 'images/grayson_erhard_profile.jpg';

    private const PROFILE_IMAGE_STORAGE_PATH = 'images/grayson_erhard_profile.jpg';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payload = $this->readPayload();
        $songs = $payload['songs'] ?? null;

        if (! is_array($songs)) {
            throw new RuntimeException('Invalid grayson_erhard_project_songs payload: songs array missing.');
        }

        $project = $this->resolveProject();

        DB::transaction(function () use ($songs, $project): void {
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

                $song = Song::query()->updateOrCreate(
                    ['normalized_key' => $normalizedKey],
                    [
                        'title' => $title,
                        'artist' => $artist,
                        'energy_level' => $this->normalizeEnergyLevel($entry['energy_level'] ?? null),
                        'genre' => $this->truncateNullableString($entry['genre'] ?? null, 50),
                        'era' => $this->truncateNullableString($entry['era'] ?? null, 50),
                        'theme' => $this->normalizeTheme($entry['theme'] ?? null),
                        'original_musical_key' => $this->normalizeKey($entry['original_musical_key'] ?? null),
                        'duration_in_seconds' => $this->normalizeDuration($entry['duration_in_seconds'] ?? null),
                    ]
                );

                ProjectSong::query()->updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'song_id' => $song->id,
                    ],
                    [
                        'energy_level' => $this->normalizeEnergyLevel($entry['energy_level'] ?? null),
                        'genre' => $this->truncateNullableString($entry['genre'] ?? null, 50),
                        'theme' => $this->normalizeTheme($entry['theme'] ?? null),
                        'performed_musical_key' => $this->normalizeKey($entry['performed_musical_key'] ?? $entry['original_musical_key'] ?? null),
                        'needs_improvement' => (bool) ($entry['needs_improvement'] ?? false),
                        'performance_count' => $this->normalizePerformanceCount($entry['performance_count'] ?? null),
                        'last_performed_at' => $this->normalizeTimestamp($entry['last_performed_at'] ?? null),
                    ]
                );
            }
        });
    }

    private function resolveProject(): Project
    {
        $profileImagePath = $this->seedPerformerProfileImage();

        $project = Project::query()->where('slug', self::PROJECT_SLUG)->first();
        if ($project instanceof Project) {
            if ($project->performer_profile_image_path !== $profileImagePath) {
                $project->update([
                    'performer_profile_image_path' => $profileImagePath,
                ]);
            }

            return $project;
        }

        $owner = User::query()->firstOrCreate(
            ['email' => self::OWNER_EMAIL],
            [
                'name' => self::OWNER_NAME,
                'password' => 'password',
            ]
        );

        return Project::query()->create([
            'owner_user_id' => $owner->id,
            'name' => self::OWNER_NAME,
            'slug' => self::PROJECT_SLUG,
            'min_tip_cents' => 500,
            'is_accepting_requests' => true,
            'performer_info_url' => 'https://www.graysonerhard.com/',
            'performer_profile_image_path' => $profileImagePath,
        ]);
    }

    private function seedPerformerProfileImage(): string
    {
        $sourcePath = public_path(self::PROFILE_IMAGE_SOURCE_PATH);
        if (! is_file($sourcePath)) {
            throw new RuntimeException("Missing performer profile image file: {$sourcePath}");
        }

        if (Storage::disk('public')->exists(self::PROFILE_IMAGE_STORAGE_PATH)) {
            return self::PROFILE_IMAGE_STORAGE_PATH;
        }

        $contents = file_get_contents($sourcePath);
        if ($contents === false) {
            throw new RuntimeException("Unable to read performer profile image file: {$sourcePath}");
        }

        $wasStored = Storage::disk('public')->put(self::PROFILE_IMAGE_STORAGE_PATH, $contents);
        if ($wasStored === false) {
            throw new RuntimeException('Unable to store performer profile image on public disk.');
        }

        return self::PROFILE_IMAGE_STORAGE_PATH;
    }

    /**
     * @return array<string, mixed>
     */
    private function readPayload(): array
    {
        $path = database_path(self::SEED_DATA_PATH);
        if (! is_file($path)) {
            throw new RuntimeException("Missing seed data file: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload)) {
            throw new RuntimeException('Invalid JSON in grayson_erhard_project_songs payload.');
        }

        return $payload;
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

    private function normalizeKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        if (! preg_match('/^[A-G](?:#|b)?m?$/', $normalized)) {
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
        if ($duration < 0 || $duration > 86400) {
            return null;
        }

        return $duration;
    }

    private function normalizeTheme(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return SongTheme::normalize($trimmed) ?? SongTheme::Story->value;
    }

    private function normalizePerformanceCount(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $count = (int) $value;

        return max(0, $count);
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
        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, $length);
    }
}
