<?php

declare(strict_types=1);

use App\Jobs\ApplyIntegrityFixes;
use App\Jobs\PollBatchResults;
use App\Mail\AdminSongIntegrityFixesMail;
use App\Models\AdminDesignation;
use App\Models\AiBatch;
use App\Models\Song;
use App\Models\SongIntegrityIssue;
use App\Services\AccountUsageService;
use App\Services\AnthropicBatchService;
use App\Services\ProjectEntitlementService;
use App\Services\SongDataIntegrityService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('builds an integrity review batch request with correct structure', function () {
    $batchService = app(AnthropicBatchService::class);
    $request = $batchService->buildIntegrityReviewRequest('integrity-1', 'test prompt');

    expect($request)->toHaveKeys(['custom_id', 'params'])
        ->and($request['custom_id'])->toBe('integrity-1')
        ->and($request['params']['max_tokens'])->toBe(512)
        ->and($request['params']['temperature'])->toBe(0.2);
});

it('processes integrity review batch results and creates issues', function () {
    config()->set('services.anthropic.api_key', 'test-key');
    $song = Song::factory()->create(['title' => 'Boheemian Rhapsody', 'artist' => 'Quen']);

    $aiBatch = AiBatch::query()->create([
        'provider' => 'anthropic',
        'batch_id' => 'batch_test_integrity',
        'batch_type' => 'integrity_review',
        'status' => 'processing',
        'request_count' => 1,
    ]);

    $batchResults = [
        [
            'custom_id' => "integrity-{$song->id}",
            'result' => [
                'type' => 'succeeded',
                'message' => [
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'issues' => [
                                [
                                    'type' => 'misspelling',
                                    'field' => 'title',
                                    'suggested_value' => 'Bohemian Rhapsody',
                                    'explanation' => 'Title is misspelled.',
                                    'severity' => 'error',
                                ],
                                [
                                    'type' => 'misspelling',
                                    'field' => 'artist',
                                    'suggested_value' => 'Queen',
                                    'explanation' => 'Artist name is misspelled.',
                                    'severity' => 'error',
                                ],
                            ],
                        ]),
                    ]],
                ],
            ],
        ],
    ];

    $statusResponse = [
        'id' => 'batch_test_integrity',
        'processing_status' => 'ended',
        'results_url' => 'https://api.anthropic.com/v1/results/batch_test_integrity',
    ];

    Http::fakeSequence('api.anthropic.com/*')
        ->push($statusResponse)
        ->push($statusResponse)
        ->push(collect($batchResults)->map(fn ($r) => json_encode($r))->implode("\n"));

    $job = new PollBatchResults;
    $job->handle(
        app(AnthropicBatchService::class),
        app(AccountUsageService::class),
        app(ProjectEntitlementService::class),
    );

    $issues = SongIntegrityIssue::where('song_id', $song->id)->get();

    expect($issues)->toHaveCount(2)
        ->and($issues[0]->issue_type)->toBe('misspelling')
        ->and($issues[0]->field)->toBe('title')
        ->and($issues[0]->suggested_value)->toBe('Bohemian Rhapsody')
        ->and($issues[0]->severity)->toBe('error')
        ->and($issues[0]->status)->toBe('open')
        ->and($issues[1]->field)->toBe('artist')
        ->and($issues[1]->suggested_value)->toBe('Queen')
        ->and($song->fresh()->last_integrity_review_at)->not->toBeNull();
});

it('does not auto-dispatch ApplyIntegrityFixes after processing integrity review batch', function () {
    Bus::fake([ApplyIntegrityFixes::class]);
    config()->set('services.anthropic.api_key', 'test-key');
    $song = Song::factory()->create(['title' => 'Boheemian Rhapsody', 'artist' => 'Queen']);

    $aiBatch = AiBatch::query()->create([
        'provider' => 'anthropic',
        'batch_id' => 'batch_test_dispatch',
        'batch_type' => 'integrity_review',
        'status' => 'processing',
        'request_count' => 1,
    ]);

    $batchResults = [
        [
            'custom_id' => "integrity-{$song->id}",
            'result' => [
                'type' => 'succeeded',
                'message' => [
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'issues' => [
                                [
                                    'type' => 'misspelling',
                                    'field' => 'title',
                                    'suggested_value' => 'Bohemian Rhapsody',
                                    'explanation' => 'Title is misspelled.',
                                    'severity' => 'error',
                                ],
                            ],
                        ]),
                    ]],
                ],
            ],
        ],
    ];

    $statusResponse = [
        'id' => 'batch_test_dispatch',
        'processing_status' => 'ended',
        'results_url' => 'https://api.anthropic.com/v1/results/batch_test_dispatch',
    ];

    Http::fakeSequence('api.anthropic.com/*')
        ->push($statusResponse)
        ->push($statusResponse)
        ->push(collect($batchResults)->map(fn ($r) => json_encode($r))->implode("\n"));

    $job = new PollBatchResults;
    $job->handle(
        app(AnthropicBatchService::class),
        app(AccountUsageService::class),
        app(ProjectEntitlementService::class),
    );

    Bus::assertNotDispatched(ApplyIntegrityFixes::class);
});

it('skips integrity results with no issues', function () {
    config()->set('services.anthropic.api_key', 'test-key');
    $song = Song::factory()->create(['title' => 'Hey Jude', 'artist' => 'The Beatles']);

    $aiBatch = AiBatch::query()->create([
        'provider' => 'anthropic',
        'batch_id' => 'batch_test_clean',
        'batch_type' => 'integrity_review',
        'status' => 'processing',
        'request_count' => 1,
    ]);

    $statusResponse = [
        'id' => 'batch_test_clean',
        'processing_status' => 'ended',
        'results_url' => 'https://api.anthropic.com/v1/results/batch_test_clean',
    ];

    Http::fakeSequence('api.anthropic.com/*')
        ->push($statusResponse)
        ->push($statusResponse)
        ->push(json_encode([
            'custom_id' => "integrity-{$song->id}",
            'result' => [
                'type' => 'succeeded',
                'message' => [
                    'content' => [[
                        'type' => 'text',
                        'text' => '{"issues": []}',
                    ]],
                ],
            ],
        ]));

    $job = new PollBatchResults;
    $job->handle(
        app(AnthropicBatchService::class),
        app(AccountUsageService::class),
        app(ProjectEntitlementService::class),
    );

    expect(SongIntegrityIssue::where('song_id', $song->id)->count())->toBe(0)
        ->and($song->fresh()->last_integrity_review_at)->not->toBeNull();
});

it('finds AI-flagged songs through the integrity service', function () {
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

    $service = app(SongDataIntegrityService::class);
    $songs = $service->findAiFlaggedSongs();

    expect($songs)->toHaveCount(1)
        ->and($songs->first()->id)->toBe($song->id);
});

it('does not return dismissed AI issues', function () {
    $song = Song::factory()->create(['title' => 'Stairway to Heaven', 'artist' => 'Led Zeppelin']);
    SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'formatting',
        'field' => 'title',
        'severity' => 'info',
        'status' => 'dismissed',
        'resolved_at' => now(),
    ]);

    $service = app(SongDataIntegrityService::class);
    $songs = $service->findAiFlaggedSongs();

    expect($songs)->toBeEmpty();
});

it('applies a suggested fix from an AI issue', function () {
    $song = Song::factory()->create(['title' => 'Boheemian Rhapsody', 'artist' => 'Queen']);
    $issue = SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'misspelling',
        'field' => 'title',
        'current_value' => 'Boheemian Rhapsody',
        'suggested_value' => 'Bohemian Rhapsody',
        'explanation' => 'Title is misspelled.',
        'severity' => 'error',
        'status' => 'open',
    ]);

    $service = app(SongDataIntegrityService::class);
    $result = $service->applySuggestedFix($issue);

    expect($result)->toBeTrue()
        ->and($song->fresh()->title)->toBe('Bohemian Rhapsody')
        ->and($issue->fresh()->status)->toBe('resolved')
        ->and($issue->fresh()->resolved_at)->not->toBeNull();
});

it('merges duplicate when applying a fix would create a duplicate song', function () {
    $canonical = Song::factory()->create(['title' => 'Bohemian Rhapsody', 'artist' => 'Queen']);
    $misspelled = Song::factory()->create(['title' => 'Boheemian Rhapsody', 'artist' => 'Queen']);

    $issue = SongIntegrityIssue::query()->create([
        'song_id' => $misspelled->id,
        'issue_type' => 'misspelling',
        'field' => 'title',
        'current_value' => 'Boheemian Rhapsody',
        'suggested_value' => 'Bohemian Rhapsody',
        'explanation' => 'Title is misspelled.',
        'severity' => 'error',
        'status' => 'open',
    ]);

    $service = app(SongDataIntegrityService::class);
    $result = $service->applySuggestedFix($issue);

    expect($result)->toBeTrue()
        ->and($issue->fresh()->status)->toBe('resolved')
        ->and(Song::find($misspelled->id))->toBeNull()
        ->and(Song::find($canonical->id))->not->toBeNull();
});

it('merges duplicate when applying artist fix would create a duplicate song', function () {
    $canonical = Song::factory()->create(['title' => 'Test Song', 'artist' => 'U2']);
    $wrong = Song::factory()->create(['title' => 'Test Song', 'artist' => 'U2 Band']);

    $issue = SongIntegrityIssue::query()->create([
        'song_id' => $wrong->id,
        'issue_type' => 'wrong_artist',
        'field' => 'artist',
        'current_value' => 'U2 Band',
        'suggested_value' => 'U2',
        'explanation' => 'Artist name is incorrect.',
        'severity' => 'error',
        'status' => 'open',
    ]);

    $service = app(SongDataIntegrityService::class);
    $result = $service->applySuggestedFix($issue);

    expect($result)->toBeTrue()
        ->and($issue->fresh()->status)->toBe('resolved')
        ->and(Song::find($wrong->id))->toBeNull()
        ->and(Song::find($canonical->id))->not->toBeNull();
});

it('merges duplicate when normalized keys collide', function () {
    $canonical = Song::factory()->create(['title' => 'Any Way You Want It', 'artist' => 'Journey']);
    $variant = Song::factory()->create(['title' => 'Anyway You Want It', 'artist' => 'Journy']);

    $issue = SongIntegrityIssue::query()->create([
        'song_id' => $variant->id,
        'issue_type' => 'misspelling',
        'field' => 'artist',
        'current_value' => 'Journy',
        'suggested_value' => 'Journey',
        'explanation' => 'Artist name is misspelled.',
        'severity' => 'error',
        'status' => 'open',
    ]);

    $service = app(SongDataIntegrityService::class);
    $result = $service->applySuggestedFix($issue);

    expect($result)->toBeTrue()
        ->and($issue->fresh()->status)->toBe('resolved')
        ->and(Song::find($variant->id))->toBeNull()
        ->and(Song::find($canonical->id))->not->toBeNull();
});

it('rejects invalid musical key suggestions', function () {
    $song = Song::factory()->create(['original_musical_key' => 'C']);
    $issue = SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'wrong_key',
        'field' => 'original_musical_key',
        'current_value' => 'C',
        'suggested_value' => 'Em7sus2',
        'severity' => 'warning',
        'status' => 'open',
    ]);

    $service = app(SongDataIntegrityService::class);
    $result = $service->applySuggestedFix($issue);

    expect($result)->toBeFalse()
        ->and($song->fresh()->original_musical_key->value)->toBe('C')
        ->and($issue->fresh()->status)->toBe('open');
});

it('merges into a soft-deleted canonical song and restores it', function () {
    $canonical = Song::factory()->create(['title' => 'Bohemian Rhapsody', 'artist' => 'Queen']);
    $canonical->delete();
    $misspelled = Song::factory()->create(['title' => 'Boheemian Rhapsody', 'artist' => 'Queen']);

    $issue = SongIntegrityIssue::query()->create([
        'song_id' => $misspelled->id,
        'issue_type' => 'misspelling',
        'field' => 'title',
        'current_value' => 'Boheemian Rhapsody',
        'suggested_value' => 'Bohemian Rhapsody',
        'severity' => 'error',
        'status' => 'open',
    ]);

    $service = app(SongDataIntegrityService::class);
    $result = $service->applySuggestedFix($issue);

    expect($result)->toBeTrue()
        ->and($issue->fresh()->status)->toBe('resolved')
        ->and(Song::withTrashed()->find($misspelled->id)->trashed())->toBeTrue()
        ->and(Song::withTrashed()->find($misspelled->id)->merged_into_song_id)->toBe($canonical->id)
        ->and(Song::find($canonical->id))->not->toBeNull()
        ->and(Song::find($canonical->id)->trashed())->toBeFalse();
});

it('returns false when the issue song is null', function () {
    $song = Song::factory()->create(['title' => 'Test', 'artist' => 'Artist']);
    $issue = SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'wrong_era',
        'field' => 'era',
        'current_value' => '80s',
        'suggested_value' => '90s',
        'severity' => 'warning',
        'status' => 'open',
    ]);

    // Simulate a soft-deleted song where BelongsTo returns null
    $song->delete();
    $issue->unsetRelation('song');

    $service = app(SongDataIntegrityService::class);
    $result = $service->applySuggestedFix($issue->fresh());

    expect($result)->toBeFalse();
});

it('deletes remaining issues when merging a duplicate', function () {
    $canonical = Song::factory()->create(['title' => 'Bohemian Rhapsody', 'artist' => 'Queen']);
    $duplicate = Song::factory()->create(['title' => 'Boheemian Rhapsody', 'artist' => 'Queen']);

    $issue1 = SongIntegrityIssue::query()->create([
        'song_id' => $duplicate->id,
        'issue_type' => 'misspelling',
        'field' => 'title',
        'current_value' => 'Boheemian Rhapsody',
        'suggested_value' => 'Bohemian Rhapsody',
        'severity' => 'error',
        'status' => 'open',
    ]);

    $issue2 = SongIntegrityIssue::query()->create([
        'song_id' => $duplicate->id,
        'issue_type' => 'wrong_era',
        'field' => 'era',
        'current_value' => '80s',
        'suggested_value' => '70s',
        'severity' => 'warning',
        'status' => 'open',
    ]);

    $service = app(SongDataIntegrityService::class);
    $service->applySuggestedFix($issue1);

    expect($issue2->fresh())->toBeNull()
        ->and(Song::withTrashed()->find($duplicate->id)->merged_into_song_id)->toBe($canonical->id);
});

it('returns open issues for a song ordered by severity', function () {
    $song = Song::factory()->create();

    SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'formatting',
        'field' => 'title',
        'severity' => 'info',
        'status' => 'open',
    ]);
    SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'misspelling',
        'field' => 'title',
        'severity' => 'error',
        'status' => 'open',
    ]);

    $service = app(SongDataIntegrityService::class);
    $issues = $service->openIssuesForSong($song);

    expect($issues)->toHaveCount(2)
        ->and($issues[0]->severity)->toBe('error')
        ->and($issues[1]->severity)->toBe('info');
});

it('dismisses an issue', function () {
    $song = Song::factory()->create();
    $issue = SongIntegrityIssue::query()->create([
        'song_id' => $song->id,
        'issue_type' => 'formatting',
        'field' => 'title',
        'severity' => 'info',
        'status' => 'open',
    ]);

    $issue->dismiss();

    expect($issue->fresh()->status)->toBe('dismissed')
        ->and($issue->fresh()->resolved_at)->not->toBeNull();
});

it('validates issue types from batch results', function () {
    config()->set('services.anthropic.api_key', 'test-key');
    $song = Song::factory()->create();

    $aiBatch = AiBatch::query()->create([
        'provider' => 'anthropic',
        'batch_id' => 'batch_test_validate',
        'batch_type' => 'integrity_review',
        'status' => 'processing',
        'request_count' => 1,
    ]);

    $statusResponse = [
        'id' => 'batch_test_validate',
        'processing_status' => 'ended',
        'results_url' => 'https://api.anthropic.com/v1/results/batch_test_validate',
    ];

    Http::fakeSequence('api.anthropic.com/*')
        ->push($statusResponse)
        ->push($statusResponse)
        ->push(json_encode([
            'custom_id' => "integrity-{$song->id}",
            'result' => [
                'type' => 'succeeded',
                'message' => [
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'issues' => [
                                ['type' => 'invalid_type', 'field' => 'title', 'severity' => 'error'],
                                ['type' => 'misspelling', 'field' => 'title', 'severity' => 'error', 'explanation' => 'Valid issue'],
                            ],
                        ]),
                    ]],
                ],
            ],
        ]));

    $job = new PollBatchResults;
    $job->handle(
        app(AnthropicBatchService::class),
        app(AccountUsageService::class),
        app(ProjectEntitlementService::class),
    );

    $issues = SongIntegrityIssue::where('song_id', $song->id)->get();

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->issue_type)->toBe('misspelling');
});

it('skips already-reviewed songs in the ai-review command', function () {
    $reviewed = Song::factory()->create([
        'title' => 'Already Reviewed',
        'artist' => 'Some Artist',
        'last_integrity_review_at' => now()->subDays(5),
    ]);
    $unreviewed = Song::factory()->create([
        'title' => 'Never Reviewed',
        'artist' => 'Another Artist',
    ]);

    $this->artisan('songs:ai-review', ['--dry-run' => true])
        ->expectsTable(
            ['ID', 'Title', 'Artist'],
            [[$unreviewed->id, 'Never Reviewed', 'Another Artist']]
        )
        ->assertSuccessful();
});

it('permanently skips reviewed songs regardless of how long ago they were reviewed', function () {
    Song::factory()->create([
        'title' => 'Old Review',
        'artist' => 'Old Artist',
        'last_integrity_review_at' => now()->subYear(),
    ]);

    $candidates = Song::query()
        ->whereNull('last_integrity_review_at')
        ->pluck('id');

    expect($candidates)->toBeEmpty();
});

it('re-reviews all songs with force flag', function () {
    Song::factory()->create([
        'title' => 'Recently Reviewed',
        'artist' => 'Artist',
        'last_integrity_review_at' => now()->subDay(),
    ]);

    $this->artisan('songs:ai-review', ['--dry-run' => true, '--force' => true])
        ->assertSuccessful();
});

it('sends admin email when fixes are applied via check-integrity', function () {
    Mail::fake();

    AdminDesignation::query()->create(['email' => 'admin@example.com']);

    Song::factory()->create([
        'title' => '  bohemian rhapsody  ',
        'artist' => 'Queen',
    ]);

    $this->artisan('songs:check-integrity', ['--check' => 'extra_whitespace', '--fix' => true])
        ->assertSuccessful();

    Mail::assertQueued(AdminSongIntegrityFixesMail::class, function ($mail) {
        return count($mail->fixes) === 1
            && $mail->fixes[0]['check'] === 'extra_whitespace'
            && $mail->fixes[0]['field'] === 'title';
    });
});

it('does not send admin email when no fixes are applied', function () {
    Mail::fake();

    AdminDesignation::query()->create(['email' => 'admin@example.com']);

    Song::factory()->create([
        'title' => 'Bohemian Rhapsody',
        'artist' => 'Queen',
    ]);

    $this->artisan('songs:check-integrity', ['--check' => 'extra_whitespace', '--fix' => true])
        ->assertSuccessful();

    Mail::assertNotQueued(AdminSongIntegrityFixesMail::class);
});
