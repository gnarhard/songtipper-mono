<?php

declare(strict_types=1);

use App\Models\Song;

it('searches songs by title or artist using LIKE', function () {
    $song = Song::factory()->create([
        'title' => 'Unique Test Title XYZ',
        'artist' => 'Test Artist ABC',
    ]);

    $found = Song::query()->search('Unique Test Title XYZ')->first();
    expect($found)->not->toBeNull();
    expect($found->id)->toBe($song->id);
});

it('normalizes non-decade era values by returning trimmed value', function () {
    // Line 244 — when era value doesn't match decade patterns, return trimmed value
    $song = Song::factory()->create(['era' => 'Classical Period']);
    expect($song->fresh()->era)->toBe('Classical Period');
});

it('normalizes era with smart quotes', function () {
    $song = Song::factory()->create(['era' => "\u{2019}90s"]);
    expect($song->fresh()->era)->toBe('90s');
});

it('normalizes 4-digit decade era with non-zero unit digit by returning as-is', function () {
    // Line 230 — decade like "1995s" where the year isn't a multiple of 10
    $song = Song::factory()->create(['era' => '1995s']);
    expect($song->fresh()->era)->toBe('1995s');
});

it('returns null for empty era string', function () {
    $song = Song::factory()->create(['era' => '']);
    expect($song->fresh()->era)->toBeNull();
});

it('returns null for whitespace-only era string', function () {
    $song = Song::factory()->create(['era' => '   ']);
    expect($song->fresh()->era)->toBeNull();
});
