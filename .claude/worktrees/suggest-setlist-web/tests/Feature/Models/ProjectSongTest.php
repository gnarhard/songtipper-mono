<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\SongPerformance;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('belongs to a project', function () {
    $model = new ProjectSong;
    $relation = $model->project();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
});

it('belongs to a song', function () {
    $model = new ProjectSong;
    $relation = $model->song();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Song::class);
});

it('has many charts', function () {
    $model = new ProjectSong;
    $relation = $model->charts();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Chart::class);
});

it('has many performances', function () {
    $model = new ProjectSong;
    $relation = $model->performances();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(SongPerformance::class);
});
