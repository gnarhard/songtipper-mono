<?php

declare(strict_types=1);

use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('belongs to a project', function () {
    $model = new ProjectMember;
    $relation = $model->project();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
});

it('belongs to a user', function () {
    $model = new ProjectMember;
    $relation = $model->user();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
});

it('identifies owner role', function () {
    $model = new ProjectMember;
    $model->role = ProjectMemberRole::Owner;

    expect($model->isOwner())->toBeTrue();

    $model->role = ProjectMemberRole::Member;
    expect($model->isOwner())->toBeFalse();

    $model->role = ProjectMemberRole::Readonly;
    expect($model->isOwner())->toBeFalse();
});

it('determines edit permission by role', function () {
    $model = new ProjectMember;

    $model->role = ProjectMemberRole::Owner;
    expect($model->canEdit())->toBeTrue();

    $model->role = ProjectMemberRole::Member;
    expect($model->canEdit())->toBeTrue();

    $model->role = ProjectMemberRole::Readonly;
    expect($model->canEdit())->toBeFalse();
});
