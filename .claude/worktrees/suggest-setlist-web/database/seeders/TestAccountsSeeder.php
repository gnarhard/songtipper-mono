<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SongTheme;
use App\Jobs\RenderChartPages;
use App\Models\Chart;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TestAccountsSeeder extends Seeder
{
    /**
     * Directory inside database/seeders/data/ where PDF charts can be placed.
     * Subdirectories: free/, basic/, pro/ — filenames should match "{title} - {artist}.pdf".
     */
    private const CHARTS_SEED_DIR = 'seeders/data/test_account_charts';

    /**
     * @var array<string, array{email: string, name: string, instrument_type: string, secondary_instrument_type: ?string, billing_plan: string, billing_status: string, slug: string, min_tip_cents: int, songs_file: string}>
     */
    private const ACCOUNTS = [
        'free' => [
            'email' => 'free@test.songtipper.com',
            'name' => 'Mia Torres',
            'instrument_type' => 'vocals',
            'secondary_instrument_type' => null,
            'billing_plan' => 'free',
            'billing_status' => 'active',
            'slug' => 'mia-torres',
            'min_tip_cents' => 500,
            'songs_file' => 'seeders/data/test_account_free_songs.json',
        ],
        'pro' => [
            'email' => 'pro@test.songtipper.com',
            'name' => 'Jake Mitchell',
            'instrument_type' => 'guitar',
            'secondary_instrument_type' => 'vocals',
            'billing_plan' => 'pro_monthly',
            'billing_status' => 'active',
            'slug' => 'jake-mitchell',
            'min_tip_cents' => 500,
            'songs_file' => 'seeders/data/test_account_pro_songs.json',
        ],
        'veteran' => [
            'email' => 'veteran@test.songtipper.com',
            'name' => 'Sarah Chen',
            'instrument_type' => 'piano',
            'secondary_instrument_type' => 'vocals',
            'billing_plan' => 'veteran_monthly',
            'billing_status' => 'active',
            'slug' => 'sarah-chen',
            'min_tip_cents' => 1000,
            'songs_file' => 'seeders/data/test_account_veteran_songs.json',
        ],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $tier => $config) {
            $this->command->info("Seeding {$tier} test account: {$config['name']} ({$config['email']})");
            $this->seedAccount($tier, $config);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function seedAccount(string $tier, array $config): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $config['email']],
            [
                'name' => $config['name'],
                'password' => 'SongTipper1234!@#$',
                'instrument_type' => $config['instrument_type'],
                'secondary_instrument_type' => $config['secondary_instrument_type'],
                'billing_plan' => $config['billing_plan'],
                'billing_status' => $config['billing_status'],
                'billing_activated_at' => now(),
            ]
        );

        // Ensure billing fields and password are up to date on existing users
        $user->forceFill([
            'password' => 'SongTipper1234!@#$',
            'email_verified_at' => $user->email_verified_at ?? now(),
            'billing_plan' => $config['billing_plan'],
            'billing_status' => $config['billing_status'],
            'billing_activated_at' => $user->billing_activated_at ?? now(),
            'instrument_type' => $config['instrument_type'],
            'secondary_instrument_type' => $config['secondary_instrument_type'],
        ])->save();

        $project = Project::query()->firstOrCreate(
            ['slug' => $config['slug']],
            [
                'owner_user_id' => $user->id,
                'name' => $config['name'],
                'min_tip_cents' => $config['min_tip_cents'],
                'is_accepting_requests' => true,
                'is_accepting_tips' => true,
            ]
        );

        $songs = $this->readSongs($config['songs_file']);

        DB::transaction(function () use ($songs, $project, $user, $tier): void {
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

                $song = Song::query()->firstOrCreate(
                    ['normalized_key' => $normalizedKey],
                    [
                        'title' => $title,
                        'artist' => $artist,
                        'energy_level' => $this->normalizeEnergyLevel($entry['energy_level'] ?? null),
                        'genre' => $this->truncate($entry['genre'] ?? null, 50),
                        'theme' => $this->normalizeTheme($entry['theme'] ?? null),
                    ]
                );

                $projectSong = ProjectSong::query()->updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'song_id' => $song->id,
                    ],
                    [
                        'energy_level' => $this->normalizeEnergyLevel($entry['energy_level'] ?? null),
                        'genre' => $this->truncate($entry['genre'] ?? null, 50),
                        'theme' => $this->normalizeTheme($entry['theme'] ?? null),
                        'performed_musical_key' => $this->normalizeKey($entry['performed_musical_key'] ?? null),
                        'needs_improvement' => (bool) ($entry['needs_improvement'] ?? false),
                        'performance_count' => max(0, (int) ($entry['performance_count'] ?? 0)),
                        'last_performed_at' => $this->normalizeTimestamp($entry['last_performed_at'] ?? null),
                    ]
                );

                $this->seedChartIfAvailable($user, $project, $song, $projectSong, $tier, $title, $artist);
            }
        });

        $songCount = $project->projectSongs()->count();
        $chartCount = $project->charts()->count();
        $this->command->info("  → {$songCount} songs, {$chartCount} charts");
    }

    private function seedChartIfAvailable(User $user, Project $project, Song $song, ProjectSong $projectSong, string $tier, string $title, string $artist): void
    {
        $filename = "{$title} - {$artist}.pdf";
        $seedPath = database_path(self::CHARTS_SEED_DIR."/{$tier}/{$filename}");

        if (! is_file($seedPath)) {
            return;
        }

        // Skip if chart already exists for this project+song
        $exists = Chart::query()
            ->where('project_id', $project->id)
            ->where('song_id', $song->id)
            ->where('project_song_id', $projectSong->id)
            ->exists();

        if ($exists) {
            return;
        }

        $contents = file_get_contents($seedPath);
        if ($contents === false) {
            return;
        }

        $sha256 = hash('sha256', $contents);
        $storagePath = "charts/{$user->id}/{$song->id}/{$filename}";

        Storage::disk('r2')->put($storagePath, $contents);

        $chart = Chart::query()->create([
            'owner_user_id' => $user->id,
            'song_id' => $song->id,
            'project_id' => $project->id,
            'project_song_id' => $projectSong->id,
            'storage_disk' => 'r2',
            'storage_path_pdf' => $storagePath,
            'original_filename' => $filename,
            'source_sha256' => $sha256,
            'file_size_bytes' => strlen($contents),
            'import_status' => 'complete',
        ]);

        RenderChartPages::dispatch($chart);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readSongs(string $relativePath): array
    {
        $path = database_path($relativePath);
        if (! is_file($path)) {
            throw new RuntimeException("Missing seed data file: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload) || ! isset($payload['songs']) || ! is_array($payload['songs'])) {
            throw new RuntimeException("Invalid seed data in: {$path}");
        }

        return $payload['songs'];
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

        return $normalized !== '' && preg_match('/^[A-G](?:#|b)?m?$/', $normalized) ? $normalized : null;
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

    private function truncate(mixed $value, int $length): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : mb_substr($normalized, 0, $length);
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
