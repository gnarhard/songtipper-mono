<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('r2');
    Storage::fake('r2-trash');
});

it('truncates all files from r2 with skip-trash and force', function (): void {
    Storage::disk('r2')->put('charts/source.pdf', 'pdf');
    Storage::disk('r2')->put('charts/renders/page-1.png', 'png');

    $this->artisan('r2:truncate', ['--force' => true, '--skip-trash' => true])
        ->expectsOutput('Deleted 2 file(s) from the root of disk [r2].')
        ->assertSuccessful();

    expect(Storage::disk('r2')->allFiles())->toBe([]);
    expect(Storage::disk('r2-trash')->allFiles())->toBe([]);
});

it('copies files to trash before truncating by default', function (): void {
    config()->set('filesystems.trash.enabled', true);

    Storage::disk('r2')->put('charts/source.pdf', 'pdf');

    $this->artisan('r2:truncate', ['--force' => true])
        ->expectsOutput('Deleted 1 file(s) from the root of disk [r2] (copied to trash first).')
        ->assertSuccessful();

    expect(Storage::disk('r2')->allFiles())->toBe([]);
    expect(Storage::disk('r2-trash')->exists('charts/source.pdf'))->toBeTrue();
    expect(Storage::disk('r2-trash')->exists('charts/source.pdf.trash-meta.json'))->toBeTrue();
});

it('truncates only the provided prefix path', function (): void {
    Storage::disk('r2')->put('charts/source.pdf', 'pdf');
    Storage::disk('r2')->put('uploads/keep.txt', 'keep');

    $this->artisan('r2:truncate', ['--path' => 'charts', '--force' => true])
        ->assertSuccessful();

    expect(Storage::disk('r2')->exists('charts/source.pdf'))->toBeFalse();
    expect(Storage::disk('r2')->exists('uploads/keep.txt'))->toBeTrue();
});

it('aborts when confirmation is denied', function (): void {
    Storage::disk('r2')->put('charts/source.pdf', 'pdf');

    $this->artisan('r2:truncate')
        ->expectsConfirmation(
            'Delete 1 file(s) from the root of disk [r2]?',
            'no',
        )
        ->expectsOutput('Command aborted. No files were deleted.')
        ->assertExitCode(1);

    expect(Storage::disk('r2')->exists('charts/source.pdf'))->toBeTrue();
});

it('fails when the disk is not configured', function (): void {
    $this->artisan('r2:truncate', ['--disk' => 'nonexistent_disk', '--force' => true])
        ->expectsOutput('Disk [nonexistent_disk] is not configured.')
        ->assertExitCode(1);
});

it('reports no files found when disk is empty', function (): void {
    $this->artisan('r2:truncate', ['--force' => true])
        ->expectsOutput('No files found in the root of disk [r2].')
        ->assertExitCode(0);
});

it('fails when an exception occurs during truncation', function (): void {
    Storage::disk('r2')->put('file.txt', 'content');

    $filesystem = Storage::disk('r2');
    $mock = Mockery::mock($filesystem);
    $mock->shouldReceive('allFiles')->andReturn(['file.txt']);
    $mock->shouldReceive('delete')->andThrow(new RuntimeException('S3 connection lost'));

    Storage::shouldReceive('disk')->with('r2')->andReturn($mock);

    $this->artisan('r2:truncate', ['--disk' => 'r2', '--force' => true, '--skip-trash' => true])
        ->expectsOutput('Failed to truncate disk [r2]: S3 connection lost')
        ->assertExitCode(1);
});
