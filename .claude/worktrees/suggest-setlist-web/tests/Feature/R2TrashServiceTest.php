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

it('soft deletes a file by copying to trash then deleting from source', function (): void {
    Storage::disk('r2')->put('charts/1/1/source.pdf', 'pdf-content');

    $service = app(R2TrashService::class);
    $result = $service->softDelete('charts/1/1/source.pdf', 'r2', [
        'trashed_by' => 'test',
    ]);

    expect($result)->toBeTrue();
    expect(Storage::disk('r2')->exists('charts/1/1/source.pdf'))->toBeFalse();
    expect(Storage::disk('r2-trash')->exists('charts/1/1/source.pdf'))->toBeTrue();
    expect(Storage::disk('r2-trash')->get('charts/1/1/source.pdf'))->toBe('pdf-content');

    $meta = json_decode(Storage::disk('r2-trash')->get('charts/1/1/source.pdf.trash-meta.json'), true);
    expect($meta['original_disk'])->toBe('r2');
    expect($meta['original_path'])->toBe('charts/1/1/source.pdf');
    expect($meta['trashed_by'])->toBe('test');
    expect($meta)->toHaveKeys(['trashed_at', 'expires_at']);
});

it('returns false when soft deleting a non-existent file', function (): void {
    $service = app(R2TrashService::class);
    $result = $service->softDelete('nonexistent.pdf', 'r2');

    expect($result)->toBeFalse();
});

it('soft deletes a directory by copying all files to trash', function (): void {
    Storage::disk('r2')->put('charts/1/1/source.pdf', 'pdf');
    Storage::disk('r2')->put('charts/1/1/renders/page-1.png', 'png1');
    Storage::disk('r2')->put('charts/1/1/renders/page-2.png', 'png2');

    $service = app(R2TrashService::class);
    $count = $service->softDeleteDirectory('charts/1/1', 'r2', [
        'trashed_by' => 'test',
    ]);

    expect($count)->toBe(3);
    expect(Storage::disk('r2')->allFiles('charts/1/1'))->toBe([]);
    expect(Storage::disk('r2-trash')->exists('charts/1/1/source.pdf'))->toBeTrue();
    expect(Storage::disk('r2-trash')->exists('charts/1/1/renders/page-1.png'))->toBeTrue();
    expect(Storage::disk('r2-trash')->exists('charts/1/1/renders/page-2.png'))->toBeTrue();
});

it('soft deletes multiple files', function (): void {
    Storage::disk('r2')->put('a.txt', 'a');
    Storage::disk('r2')->put('b.txt', 'b');

    $service = app(R2TrashService::class);
    $count = $service->softDeleteFiles(['a.txt', 'b.txt'], 'r2', [
        'trashed_by' => 'test',
    ]);

    expect($count)->toBe(2);
    expect(Storage::disk('r2')->exists('a.txt'))->toBeFalse();
    expect(Storage::disk('r2')->exists('b.txt'))->toBeFalse();
    expect(Storage::disk('r2-trash')->exists('a.txt'))->toBeTrue();
    expect(Storage::disk('r2-trash')->exists('b.txt'))->toBeTrue();
});

it('performs direct delete when trash is disabled', function (): void {
    config(['filesystems.trash.enabled' => false]);
    Storage::disk('r2')->put('file.txt', 'content');

    $service = new R2TrashService;
    $result = $service->softDelete('file.txt', 'r2');

    expect($result)->toBeTrue();
    expect(Storage::disk('r2')->exists('file.txt'))->toBeFalse();
    expect(Storage::disk('r2-trash')->allFiles())->toBe([]);
});

it('performs direct directory delete when trash is disabled', function (): void {
    config(['filesystems.trash.enabled' => false]);
    Storage::disk('r2')->put('charts/1/source.pdf', 'pdf');

    $service = new R2TrashService;
    $count = $service->softDeleteDirectory('charts/1', 'r2');

    expect($count)->toBe(0);
    expect(Storage::disk('r2')->allFiles('charts/1'))->toBe([]);
    expect(Storage::disk('r2-trash')->allFiles())->toBe([]);
});

it('restores a file from trash back to the target disk', function (): void {
    Storage::disk('r2')->put('source.pdf', 'original');
    $service = app(R2TrashService::class);
    $service->softDelete('source.pdf', 'r2', ['trashed_by' => 'test']);

    expect(Storage::disk('r2')->exists('source.pdf'))->toBeFalse();

    $result = $service->restore('source.pdf', 'r2');

    expect($result)->toBeTrue();
    expect(Storage::disk('r2')->exists('source.pdf'))->toBeTrue();
    expect(Storage::disk('r2')->get('source.pdf'))->toBe('original');
    expect(Storage::disk('r2-trash')->exists('source.pdf'))->toBeFalse();
    expect(Storage::disk('r2-trash')->exists('source.pdf.trash-meta.json'))->toBeFalse();
});

it('restores a directory from trash', function (): void {
    Storage::disk('r2')->put('charts/1/a.pdf', 'a');
    Storage::disk('r2')->put('charts/1/b.png', 'b');

    $service = app(R2TrashService::class);
    $service->softDeleteDirectory('charts/1', 'r2', ['trashed_by' => 'test']);

    $count = $service->restoreDirectory('charts/1', 'r2');

    expect($count)->toBe(2);
    expect(Storage::disk('r2')->exists('charts/1/a.pdf'))->toBeTrue();
    expect(Storage::disk('r2')->exists('charts/1/b.png'))->toBeTrue();
});

it('returns false when restoring a non-existent trash file', function (): void {
    $service = app(R2TrashService::class);
    $result = $service->restore('nonexistent.pdf', 'r2');

    expect($result)->toBeFalse();
});

it('purges expired trash items', function (): void {
    Storage::disk('r2-trash')->put('old.pdf', 'old-content');
    Storage::disk('r2-trash')->put('old.pdf.trash-meta.json', json_encode([
        'original_disk' => 'r2',
        'original_path' => 'old.pdf',
        'trashed_at' => now()->subDays(60)->toIso8601String(),
        'trashed_by' => 'test',
        'expires_at' => now()->subDays(30)->toIso8601String(),
    ]));

    Storage::disk('r2-trash')->put('new.pdf', 'new-content');
    Storage::disk('r2-trash')->put('new.pdf.trash-meta.json', json_encode([
        'original_disk' => 'r2',
        'original_path' => 'new.pdf',
        'trashed_at' => now()->toIso8601String(),
        'trashed_by' => 'test',
        'expires_at' => now()->addDays(30)->toIso8601String(),
    ]));

    $service = app(R2TrashService::class);
    $purged = $service->purgeExpired();

    expect($purged)->toBe(1);
    expect(Storage::disk('r2-trash')->exists('old.pdf'))->toBeFalse();
    expect(Storage::disk('r2-trash')->exists('old.pdf.trash-meta.json'))->toBeFalse();
    expect(Storage::disk('r2-trash')->exists('new.pdf'))->toBeTrue();
    expect(Storage::disk('r2-trash')->exists('new.pdf.trash-meta.json'))->toBeTrue();
});

it('lists trash contents', function (): void {
    Storage::disk('r2-trash')->put('file.pdf', 'content');
    Storage::disk('r2-trash')->put('file.pdf.trash-meta.json', json_encode([
        'original_disk' => 'r2',
        'original_path' => 'file.pdf',
        'trashed_at' => '2026-03-17T10:00:00+00:00',
        'trashed_by' => 'test',
        'expires_at' => '2026-04-16T10:00:00+00:00',
    ]));

    $service = app(R2TrashService::class);
    $items = $service->listTrash();

    expect($items)->toHaveCount(1);
    expect($items[0]['path'])->toBe('file.pdf');
    expect($items[0]['trashed_by'])->toBe('test');
});

it('lists trash filtered by prefix', function (): void {
    Storage::disk('r2-trash')->put('charts/1/a.pdf', 'a');
    Storage::disk('r2-trash')->put('charts/1/a.pdf.trash-meta.json', json_encode([
        'original_disk' => 'r2',
        'original_path' => 'charts/1/a.pdf',
        'trashed_at' => now()->toIso8601String(),
        'trashed_by' => 'test',
        'expires_at' => now()->addDays(30)->toIso8601String(),
    ]));
    Storage::disk('r2-trash')->put('other/b.txt', 'b');
    Storage::disk('r2-trash')->put('other/b.txt.trash-meta.json', json_encode([
        'original_disk' => 'r2',
        'original_path' => 'other/b.txt',
        'trashed_at' => now()->toIso8601String(),
        'trashed_by' => 'test',
        'expires_at' => now()->addDays(30)->toIso8601String(),
    ]));

    $service = app(R2TrashService::class);
    $items = $service->listTrash('charts');

    expect($items)->toHaveCount(1);
    expect($items[0]['path'])->toBe('charts/1/a.pdf');
});

it('handles empty paths array gracefully', function (): void {
    $service = app(R2TrashService::class);
    $count = $service->softDeleteFiles([], 'r2');

    expect($count)->toBe(0);
});

it('reports enabled status correctly', function (): void {
    $service = app(R2TrashService::class);
    expect($service->isEnabled())->toBeTrue();

    config(['filesystems.trash.enabled' => false]);
    $disabled = new R2TrashService;
    expect($disabled->isEnabled())->toBeFalse();
});
