<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\ChartAnnotationVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

it('belongs to a chart', function () {
    $model = new ChartAnnotationVersion;
    $relation = $model->chart();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Chart::class);
});

it('belongs to an owner', function () {
    $model = new ChartAnnotationVersion;
    $relation = $model->owner();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getForeignKeyName())->toBe('owner_user_id');
});

it('casts page_number as integer', function () {
    $model = new ChartAnnotationVersion;
    $model->page_number = '3';

    expect($model->page_number)->toBeInt()->toBe(3);
});

it('casts strokes as array', function () {
    $model = new ChartAnnotationVersion;
    $model->strokes = [['x' => 10, 'y' => 20]];

    expect($model->strokes)->toBeArray();
    expect($model->strokes[0]['x'])->toBe(10);
});

it('casts client_created_at as datetime', function () {
    $model = new ChartAnnotationVersion;
    $model->client_created_at = '2026-03-15 10:30:00';

    expect($model->client_created_at)->toBeInstanceOf(Carbon::class);
});
