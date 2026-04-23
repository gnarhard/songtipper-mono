<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\ChartAnnotationVersion;
use App\Models\ChartPageUserPref;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('belongs to an owner', function () {
    $model = new Chart;
    $relation = $model->owner();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getForeignKeyName())->toBe('owner_user_id');
});

it('has many annotation versions', function () {
    $model = new Chart;
    $relation = $model->annotationVersions();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(ChartAnnotationVersion::class);
});

it('has many page user prefs', function () {
    $model = new Chart;
    $relation = $model->pageUserPrefs();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(ChartPageUserPref::class);
});

it('returns the storage directory', function () {
    $model = new Chart;
    $model->owner_user_id = 42;
    $model->id = 99;

    expect($model->getStorageDirectory())->toBe('charts/42/99');
});
