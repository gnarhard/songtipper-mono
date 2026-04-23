<?php

declare(strict_types=1);

use App\Models\Song;

it('normalizes 1900s decade eras to short format when saved', function () {
    $song = Song::factory()->create([
        'era' => "1990's",
    ]);

    expect($song->fresh()->era)->toBe('90s');
});

it('keeps 2000s and later decade eras in full format when saved', function () {
    $song = Song::factory()->create([
        'era' => "2010's",
    ]);

    expect($song->fresh()->era)->toBe('2010s');
});
