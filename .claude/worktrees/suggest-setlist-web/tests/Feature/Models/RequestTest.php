<?php

declare(strict_types=1);

use App\Models\AudienceProfile;
use App\Models\PerformanceSession;
use App\Models\Project;
use App\Models\Request;
use App\Models\Song;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('belongs to a project', function () {
    $model = new Request;
    $relation = $model->project();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
});

it('belongs to an audience profile', function () {
    $model = new Request;
    $relation = $model->audienceProfile();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(AudienceProfile::class);
});

it('belongs to a performance session', function () {
    $model = new Request;
    $relation = $model->performanceSession();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(PerformanceSession::class);
});

it('belongs to a song', function () {
    $model = new Request;
    $relation = $model->song();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Song::class);
});

it('formats tip amount as dollars', function () {
    $model = new Request;
    $model->tip_amount_cents = 1500;

    expect($model->tip_amount_dollars)->toBe('15');
});
