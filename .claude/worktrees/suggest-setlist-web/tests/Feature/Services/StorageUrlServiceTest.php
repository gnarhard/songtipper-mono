<?php

declare(strict_types=1);

use App\Services\StorageUrlService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('test-disk');
    config()->set('filesystems.chart', 'test-disk');
});

it('uses the configured chart disk by default', function () {
    $service = new StorageUrlService;

    Storage::disk('test-disk')->put('test.txt', 'content');

    expect($service->exists('test.txt'))->toBeTrue();
    expect($service->exists('nonexistent.txt'))->toBeFalse();
});

it('accepts an explicit disk name', function () {
    Storage::fake('custom-disk');
    $service = new StorageUrlService(disk: 'custom-disk');

    Storage::disk('custom-disk')->put('file.txt', 'data');

    expect($service->exists('file.txt'))->toBeTrue();
});

it('generates a signed url via signedUrl', function () {
    $fakeDisk = Storage::fake('signed-disk');
    $service = new StorageUrlService(disk: 'signed-disk');

    $url = $service->signedUrl('charts/doc.pdf');

    expect($url)->toBeString()->toContain('charts/doc.pdf');
});

it('generates a chart pdf url', function () {
    $fakeDisk = Storage::fake('pdf-disk');
    $service = new StorageUrlService(disk: 'pdf-disk');

    $url = $service->chartPdfUrl('charts/my.pdf', 120);

    expect($url)->toBeString()->toContain('charts/my.pdf');
});

it('generates a chart render url', function () {
    $fakeDisk = Storage::fake('render-disk');
    $service = new StorageUrlService(disk: 'render-disk');

    $url = $service->chartRenderUrl('renders/page1.png', 30);

    expect($url)->toBeString()->toContain('renders/page1.png');
});

it('deletes a file', function () {
    $service = new StorageUrlService;

    Storage::disk('test-disk')->put('to-delete.txt', 'content');
    expect($service->exists('to-delete.txt'))->toBeTrue();

    $result = $service->delete('to-delete.txt');

    expect($result)->toBeTrue();
    expect($service->exists('to-delete.txt'))->toBeFalse();
});

it('deletes a directory', function () {
    $service = new StorageUrlService;

    Storage::disk('test-disk')->put('mydir/file1.txt', 'a');
    Storage::disk('test-disk')->put('mydir/file2.txt', 'b');

    $result = $service->deleteDirectory('mydir');

    expect($result)->toBeTrue();
    expect($service->exists('mydir/file1.txt'))->toBeFalse();
    expect($service->exists('mydir/file2.txt'))->toBeFalse();
});
