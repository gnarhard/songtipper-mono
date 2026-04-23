<?php

declare(strict_types=1);

use App\Models\PerformanceSession;
use App\Models\PerformanceSessionItem;
use App\Models\Project;
use App\Models\Request;
use App\Models\Setlist;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('belongs to a project', function () {
    $model = new PerformanceSession;
    $relation = $model->project();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
});

it('belongs to a setlist', function () {
    $model = new PerformanceSession;
    $relation = $model->setlist();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Setlist::class);
});

it('has many items', function () {
    $model = new PerformanceSession;
    $relation = $model->items();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(PerformanceSessionItem::class);
});

it('has many requests', function () {
    $model = new PerformanceSession;
    $relation = $model->requests();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Request::class);
});
