<?php

declare(strict_types=1);

use App\Jobs\ApplyIntegrityFixes;
use App\Mail\AdminSongIntegrityFixesMail;
use App\Models\AdminDesignation;
use App\Models\Song;
use App\Models\SongIntegrityIssue;
use Illuminate\Support\Facades\Mail;

it('applies suggested fixes for songs with open issues', function () {
    $song = Song::factory()->create(['title' => 'Boheemian Rhapsody', 'artist' => 'Queen']);

    SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'misspelling',
        'field' => 'title',
        'current_value' => 'Boheemian Rhapsody',
        'suggested_value' => 'Bohemian Rhapsody',
        'explanation' => 'Title is misspelled.',
        'severity' => 'error',
        'status' => 'open',
    ]);

    Mail::fake();

    $job = new ApplyIntegrityFixes([$song->id]);
    app()->call([$job, 'handle']);

    expect($song->fresh()->title)->toBe('Bohemian Rhapsody')
        ->and(SongIntegrityIssue::where('song_id', $song->id)->first()->status)->toBe('resolved');
});

it('sends admin notification after applying fixes', function () {
    $song = Song::factory()->create(['title' => 'Boheemian Rhapsody', 'artist' => 'Queen']);

    SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'misspelling',
        'field' => 'title',
        'current_value' => 'Boheemian Rhapsody',
        'suggested_value' => 'Bohemian Rhapsody',
        'explanation' => 'Title is misspelled.',
        'severity' => 'error',
        'status' => 'open',
    ]);

    AdminDesignation::query()->create(['email' => 'admin@example.com']);

    Mail::fake();

    $job = new ApplyIntegrityFixes([$song->id]);
    app()->call([$job, 'handle']);

    Mail::assertQueued(AdminSongIntegrityFixesMail::class, function ($mail) {
        return count($mail->fixes) === 1
            && $mail->fixes[0]['field'] === 'title'
            && $mail->fixes[0]['new_value'] === 'Bohemian Rhapsody';
    });
});

it('skips issues without a suggested value', function () {
    $song = Song::factory()->create(['title' => 'Test Song', 'artist' => 'Test Artist']);

    SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'possible_duplicate',
        'field' => null,
        'suggested_value' => null,
        'explanation' => 'Possible duplicate of song #42.',
        'severity' => 'warning',
        'status' => 'open',
    ]);

    Mail::fake();

    $job = new ApplyIntegrityFixes([$song->id]);
    app()->call([$job, 'handle']);

    expect(SongIntegrityIssue::where('song_id', $song->id)->first()->status)->toBe('open');
    Mail::assertNothingQueued();
});

it('skips already resolved or dismissed issues', function () {
    $song = Song::factory()->create(['title' => 'Boheemian Rhapsody', 'artist' => 'Queen']);

    SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'misspelling',
        'field' => 'title',
        'current_value' => 'Boheemian Rhapsody',
        'suggested_value' => 'Bohemian Rhapsody',
        'severity' => 'error',
        'status' => 'resolved',
        'resolved_at' => now(),
    ]);

    SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'misspelling',
        'field' => 'artist',
        'current_value' => 'Queen',
        'suggested_value' => 'Queen',
        'severity' => 'error',
        'status' => 'dismissed',
        'resolved_at' => now(),
    ]);

    Mail::fake();

    $job = new ApplyIntegrityFixes([$song->id]);
    app()->call([$job, 'handle']);

    expect($song->fresh()->title)->toBe('Boheemian Rhapsody');
    Mail::assertNothingQueued();
});

it('does nothing when given empty song IDs', function () {
    Mail::fake();

    $job = new ApplyIntegrityFixes([]);
    app()->call([$job, 'handle']);

    Mail::assertNothingQueued();
});

it('applies fixes for multiple songs in one batch', function () {
    $song1 = Song::factory()->create(['title' => 'Boheemian Rhapsody', 'artist' => 'Queen']);
    $song2 = Song::factory()->create(['title' => 'Imagin', 'artist' => 'John Lennon']);

    SongIntegrityIssue::query()->create([
        'song_id' => $song1->id,
        'issue_type' => 'misspelling',
        'field' => 'title',
        'current_value' => 'Boheemian Rhapsody',
        'suggested_value' => 'Bohemian Rhapsody',
        'severity' => 'error',
        'status' => 'open',
    ]);

    SongIntegrityIssue::query()->create([
        'song_id' => $song2->id,
        'issue_type' => 'misspelling',
        'field' => 'title',
        'current_value' => 'Imagin',
        'suggested_value' => 'Imagine',
        'severity' => 'error',
        'status' => 'open',
    ]);

    Mail::fake();

    $job = new ApplyIntegrityFixes([$song1->id, $song2->id]);
    app()->call([$job, 'handle']);

    expect($song1->fresh()->title)->toBe('Bohemian Rhapsody')
        ->and($song2->fresh()->title)->toBe('Imagine');

    Mail::assertQueued(AdminSongIntegrityFixesMail::class, function ($mail) {
        return count($mail->fixes) === 2;
    });
});
