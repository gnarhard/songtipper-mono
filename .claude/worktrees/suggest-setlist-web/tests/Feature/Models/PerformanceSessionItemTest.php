<?php

declare(strict_types=1);

use App\Enums\PerformanceSessionItemStatus;
use App\Models\PerformanceSession;
use App\Models\PerformanceSessionItem;
use App\Models\ProjectSong;
use App\Models\SetlistSet;
use App\Models\SetlistSong;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

it('belongs to a performance session', function () {
    $model = new PerformanceSessionItem;
    $relation = $model->performanceSession();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(PerformanceSession::class);
});

it('belongs to a setlist set', function () {
    $model = new PerformanceSessionItem;
    $relation = $model->setlistSet();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(SetlistSet::class);
    expect($relation->getForeignKeyName())->toBe('setlist_set_id');
});

it('belongs to a setlist song', function () {
    $model = new PerformanceSessionItem;
    $relation = $model->setlistSong();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(SetlistSong::class);
});

it('belongs to a project song', function () {
    $model = new PerformanceSessionItem;
    $relation = $model->projectSong();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(ProjectSong::class);
});

it('casts status as PerformanceSessionItemStatus enum', function () {
    $item = PerformanceSessionItem::factory()->create([
        'status' => 'performed',
    ]);

    expect($item->refresh()->status)->toBe(PerformanceSessionItemStatus::Performed);
});

it('casts order fields as integers', function () {
    $item = PerformanceSessionItem::factory()->create([
        'order_index' => 3,
        'performed_order_index' => 1,
    ]);

    $item->refresh();
    expect($item->order_index)->toBeInt();
    expect($item->performed_order_index)->toBeInt();
});

it('casts datetime fields correctly', function () {
    $item = PerformanceSessionItem::factory()->create([
        'performed_at' => '2026-03-15 10:00:00',
    ]);

    expect($item->refresh()->performed_at)->toBeInstanceOf(Carbon::class);
});
