<?php

declare(strict_types=1);

use App\Services\R2TrashService;
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

it('restores a single file from trash', function (): void {
    Storage::disk('r2')->put('charts/1/source.pdf', 'pdf-content');
    app(R2TrashService::class)->softDelete('charts/1/source.pdf', 'r2', ['trashed_by' => 'test']);

    $this->artisan('r2:restore', ['path' => 'charts/1/source.pdf'])
        ->expectsOutput('Restored 1 file(s) from trash to disk [r2].')
        ->assertSuccessful();

    expect(Storage::disk('r2')->exists('charts/1/source.pdf'))->toBeTrue();
    expect(Storage::disk('r2')->get('charts/1/source.pdf'))->toBe('pdf-content');
});

it('restores a directory from trash', function (): void {
    Storage::disk('r2')->put('charts/1/a.pdf', 'a');
    Storage::disk('r2')->put('charts/1/b.png', 'b');
    app(R2TrashService::class)->softDeleteDirectory('charts/1', 'r2', ['trashed_by' => 'test']);

    $this->artisan('r2:restore', ['path' => 'charts/1'])
        ->expectsOutput('Restored 2 file(s) from trash to disk [r2].')
        ->assertSuccessful();
});

it('warns when no files found in trash', function (): void {
    $this->artisan('r2:restore', ['path' => 'nonexistent'])
        ->expectsOutput('No files found in trash matching [nonexistent].')
        ->assertExitCode(1);
});

it('fails when trash is disabled', function (): void {
    config(['filesystems.trash.enabled' => false]);

    $this->artisan('r2:restore', ['path' => 'charts/1'])
        ->expectsOutput('R2 trash is disabled. Cannot restore.')
        ->assertExitCode(1);
});
