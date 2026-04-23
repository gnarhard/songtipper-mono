<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Setlist;
use App\Models\SetlistShareAcceptance;
use App\Models\SetlistShareLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('uses token as route key name', function () {
    $model = new SetlistShareLink;

    expect($model->getRouteKeyName())->toBe('token');
});

it('belongs to a project', function () {
    $model = new SetlistShareLink;
    $relation = $model->project();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
});

it('belongs to a setlist', function () {
    $model = new SetlistShareLink;
    $relation = $model->setlist();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Setlist::class);
});

it('belongs to a creator', function () {
    $model = new SetlistShareLink;
    $relation = $model->creator();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getForeignKeyName())->toBe('created_by_user_id');
});

it('has many acceptances', function () {
    $model = new SetlistShareLink;
    $relation = $model->acceptances();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(SetlistShareAcceptance::class);
});
