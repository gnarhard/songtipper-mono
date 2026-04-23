<?php

declare(strict_types=1);

use App\Models\PerformanceSession;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistSong;
use App\Models\SongPerformance;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('belongs to a project', function () {
    $model = new SongPerformance;
    $relation = $model->project();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
});

it('belongs to a project song', function () {
    $model = new SongPerformance;
    $relation = $model->projectSong();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(ProjectSong::class);
});

it('belongs to a performance session', function () {
    $model = new SongPerformance;
    $relation = $model->performanceSession();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(PerformanceSession::class);
});

it('belongs to a performer', function () {
    $model = new SongPerformance;
    $relation = $model->performer();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getForeignKeyName())->toBe('performer_user_id');
});

it('belongs to a setlist', function () {
    $model = new SongPerformance;
    $relation = $model->setlist();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Setlist::class);
});

it('belongs to a set', function () {
    $model = new SongPerformance;
    $relation = $model->set();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(SetlistSet::class);
    expect($relation->getForeignKeyName())->toBe('set_id');
});

it('belongs to a setlist song', function () {
    $model = new SongPerformance;
    $relation = $model->setlistSong();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(SetlistSong::class);
});
