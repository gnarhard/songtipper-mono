<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\ChartPageUserPref;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('has fillable attributes', function () {
    $pref = new ChartPageUserPref;

    expect($pref->getFillable())->toBe([
        'chart_id',
        'owner_user_id',
        'page_number',
        'zoom_scale',
        'offset_dx',
        'offset_dy',
    ]);
});

it('casts attributes correctly', function () {
    $pref = new ChartPageUserPref;
    $casts = $pref->getCasts();

    expect($casts['page_number'])->toBe('integer');
    expect($casts['zoom_scale'])->toBe('float');
    expect($casts['offset_dx'])->toBe('float');
    expect($casts['offset_dy'])->toBe('float');
});

it('belongs to a chart', function () {
    $pref = new ChartPageUserPref;
    $relation = $pref->chart();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Chart::class);
});

it('belongs to an owner user', function () {
    $pref = new ChartPageUserPref;
    $relation = $pref->owner();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getForeignKeyName())->toBe('owner_user_id');
});
