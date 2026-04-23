<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('r2');
    Storage::fake('r2-trash');

    config([
        'filesystems.trash.disk' => 'r2-trash',
        'filesystems.trash.retention_days' => 30,
        'filesystems.trash.enabled' => true,
    ]);
});

it('purges expired trash items', function (): void {
    Storage::disk('r2-trash')->put('expired.pdf', 'content');
    Storage::disk('r2-trash')->put('expired.pdf.trash-meta.json', json_encode([
        'original_disk' => 'r2',
        'original_path' => 'expired.pdf',
        'trashed_at' => now()->subDays(60)->toIso8601String(),
        'trashed_by' => 'test',
        'expires_at' => now()->subDays(1)->toIso8601String(),
    ]));

    $this->artisan('r2:cleanup-trash')
        ->expectsOutput('Purged 1 expired item(s) from trash.')
        ->assertSuccessful();

    expect(Storage::disk('r2-trash')->exists('expired.pdf'))->toBeFalse();
});

it('does not purge non-expired items', function (): void {
    Storage::disk('r2-trash')->put('fresh.pdf', 'content');
    Storage::disk('r2-trash')->put('fresh.pdf.trash-meta.json', json_encode([
        'original_disk' => 'r2',
        'original_path' => 'fresh.pdf',
        'trashed_at' => now()->toIso8601String(),
        'trashed_by' => 'test',
        'expires_at' => now()->addDays(30)->toIso8601String(),
    ]));

    $this->artisan('r2:cleanup-trash')
        ->expectsOutput('Purged 0 expired item(s) from trash.')
        ->assertSuccessful();

    expect(Storage::disk('r2-trash')->exists('fresh.pdf'))->toBeTrue();
});

it('warns when trash is disabled', function (): void {
    config(['filesystems.trash.enabled' => false]);

    $this->artisan('r2:cleanup-trash')
        ->expectsOutput('R2 trash is disabled. Nothing to clean up.')
        ->assertSuccessful();
});
