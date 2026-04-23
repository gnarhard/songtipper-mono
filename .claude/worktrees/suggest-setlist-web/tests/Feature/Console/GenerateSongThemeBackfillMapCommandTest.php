<?php

declare(strict_types=1);

use App\Models\Song;
use App\Services\SongMetadataAiRepository;
use Illuminate\Support\Facades\File;

it('writes a deterministic canonical theme map using AI metadata', function () {
    $songA = Song::factory()->create([
        'title' => 'Love Story',
        'artist' => 'Taylor Swift',
        'normalized_key' => Song::generateNormalizedKey('Love Story', 'Taylor Swift'),
    ]);

    $songB = Song::factory()->create([
        'title' => 'Untitled Song',
        'artist' => 'Unknown Artist',
        'normalized_key' => Song::generateNormalizedKey('Untitled Song', 'Unknown Artist'),
    ]);

    $aiRepository = mock(SongMetadataAiRepository::class);
    $aiRepository->shouldReceive('isEnabled')->once()->andReturn(true);
    $aiRepository->shouldReceive('enrichMetadataFromTitleAndArtist')
        ->once()
        ->with('Love Story', 'Taylor Swift')
        ->andReturn(['theme' => 'Love']);
    $aiRepository->shouldReceive('enrichMetadataFromTitleAndArtist')
        ->once()
        ->with('Untitled Song', 'Unknown Artist')
        ->andReturnNull();

    app()->instance(SongMetadataAiRepository::class, $aiRepository);

    $outputFileName = 'song_theme_backfill_map_test.php';
    $outputPath = database_path('data/'.$outputFileName);
    File::delete($outputPath);

    $this->artisan('songs:generate-theme-backfill-map', [
        '--output' => $outputFileName,
        '--force' => true,
    ])->assertExitCode(0);

    expect(File::exists($outputPath))->toBeTrue();

    /** @var array{fallback: string, themes_by_normalized_key: array<string, string>} $map */
    $map = require $outputPath;

    expect($map['fallback'])->toBe('story');
    expect($map['themes_by_normalized_key'][$songA->normalized_key])->toBe('love');
    expect($map['themes_by_normalized_key'][$songB->normalized_key])->toBe('story');

    File::delete($outputPath);
});

it('fails when AI metadata provider is disabled', function () {
    $aiRepository = mock(SongMetadataAiRepository::class);
    $aiRepository->shouldReceive('isEnabled')->once()->andReturn(false);

    app()->instance(SongMetadataAiRepository::class, $aiRepository);

    $this->artisan('songs:generate-theme-backfill-map', [
        '--output' => 'song_theme_backfill_map_test_disabled.php',
        '--force' => true,
    ])->assertExitCode(1);
});

it('fails when output filename is unsafe', function () {
    $aiRepository = mock(SongMetadataAiRepository::class);
    $aiRepository->shouldReceive('isEnabled')->once()->andReturn(true);

    app()->instance(SongMetadataAiRepository::class, $aiRepository);

    $this->artisan('songs:generate-theme-backfill-map', [
        '--output' => '../etc/evil.php',
        '--force' => true,
    ])
        ->expectsOutput('The --output value must be a safe filename under database/data.')
        ->assertExitCode(1);
});

it('fails when output file already exists and force is not used', function () {
    $aiRepository = mock(SongMetadataAiRepository::class);
    $aiRepository->shouldReceive('isEnabled')->once()->andReturn(true);

    app()->instance(SongMetadataAiRepository::class, $aiRepository);

    $outputFileName = 'song_theme_backfill_map_exists_test.php';
    $outputPath = database_path('data/'.$outputFileName);

    File::ensureDirectoryExists(dirname($outputPath));
    File::put($outputPath, '<?php return [];');

    $this->artisan('songs:generate-theme-backfill-map', [
        '--output' => $outputFileName,
    ])
        ->expectsOutput("Output file already exists: {$outputPath}. Use --force to overwrite.")
        ->assertExitCode(1);

    File::delete($outputPath);
});
