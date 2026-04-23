<?php

declare(strict_types=1);

use App\Enums\EnergyLevel;
use App\Jobs\EnrichImportedSongs;
use App\Models\Project;
use App\Models\Song;
use App\Models\User;
use App\Services\SongMetadataLookupService;
use Mockery\MockInterface;

it('enriches songs that are missing metadata', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $song = Song::factory()->create([
        'title' => 'Bohemian Rhapsody',
        'artist' => 'Queen',
        'energy_level' => null,
        'era' => null,
        'genre' => null,
        'theme' => null,
    ]);

    $this->mock(SongMetadataLookupService::class, function (MockInterface $mock) {
        $mock->shouldReceive('lookup')
            ->once()
            ->andReturn([
                'metadata' => [
                    'energy_level' => 'high',
                    'era' => '70s',
                    'genre' => 'Rock',
                    'theme' => 'Rebellion',
                ],
            ]);
    });

    (new EnrichImportedSongs([$song->id], $project->id))->handle(
        app(SongMetadataLookupService::class)
    );

    $song->refresh();
    expect($song->energy_level)->toBe(EnergyLevel::High);
    expect($song->genre)->toBe('Rock');
    expect($song->theme)->toBe('Rebellion');
});

it('skips songs that already have complete metadata', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $song = Song::factory()->create([
        'title' => 'Test Song',
        'artist' => 'Test Artist',
        'energy_level' => EnergyLevel::Medium,
        'era' => '2020s',
        'genre' => 'Pop',
        'theme' => 'Love',
        'original_musical_key' => 'C',
        'duration_in_seconds' => 240,
    ]);

    $this->mock(SongMetadataLookupService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('lookup');
    });

    (new EnrichImportedSongs([$song->id], $project->id))->handle(
        app(SongMetadataLookupService::class)
    );
});

it('does not overwrite existing fields with enrichment data', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $song = Song::factory()->create([
        'title' => 'Test Song',
        'artist' => 'Test Artist',
        'energy_level' => EnergyLevel::Low,
        'era' => null,
        'genre' => null,
        'theme' => null,
    ]);

    $this->mock(SongMetadataLookupService::class, function (MockInterface $mock) {
        $mock->shouldReceive('lookup')
            ->once()
            ->andReturn([
                'metadata' => [
                    'energy_level' => 'high',
                    'era' => '90s',
                    'genre' => 'Rock',
                ],
            ]);
    });

    (new EnrichImportedSongs([$song->id], $project->id))->handle(
        app(SongMetadataLookupService::class)
    );

    $song->refresh();
    expect($song->energy_level)->toBe(EnergyLevel::Low); // preserved
    expect($song->era)->not->toBeNull(); // enriched
    expect($song->genre)->toBe('Rock'); // enriched
});

it('handles missing song IDs gracefully', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    $this->mock(SongMetadataLookupService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('lookup');
    });

    (new EnrichImportedSongs([999999], $project->id))->handle(
        app(SongMetadataLookupService::class)
    );
});

it('continues processing when one song lookup fails', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $song1 = Song::factory()->create([
        'title' => 'Song 1',
        'artist' => 'Artist 1',
        'energy_level' => null,
    ]);
    $song2 = Song::factory()->create([
        'title' => 'Song 2',
        'artist' => 'Artist 2',
        'energy_level' => null,
    ]);

    $callCount = 0;
    $this->mock(SongMetadataLookupService::class, function (MockInterface $mock) use (&$callCount) {
        $mock->shouldReceive('lookup')
            ->twice()
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new RuntimeException('API error');
                }

                return ['metadata' => ['energy_level' => 'medium']];
            });
    });

    (new EnrichImportedSongs([$song1->id, $song2->id], $project->id))->handle(
        app(SongMetadataLookupService::class)
    );

    // Song 2 should still be enriched even though Song 1 failed.
    $song2->refresh();
    expect($song2->energy_level)->toBe(EnergyLevel::Medium);
});
