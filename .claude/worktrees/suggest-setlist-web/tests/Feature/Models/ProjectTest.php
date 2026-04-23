<?php

declare(strict_types=1);

use App\Models\AudienceProfile;
use App\Models\Chart;
use App\Models\PerformanceSession;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectSong;
use App\Models\Request;
use App\Models\Setlist;
use App\Models\SetlistShareLink;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('returns normalizeQuickTipAmounts default when not 3 amounts', function () {
    expect(Project::normalizeQuickTipAmounts([1000, 500]))
        ->toBe(Project::DEFAULT_QUICK_TIP_AMOUNTS_CENTS);
});

it('belongs to an owner', function () {
    $model = new Project;
    $relation = $model->owner();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
    expect($relation->getForeignKeyName())->toBe('owner_user_id');
});

it('belongs to many members', function () {
    $model = new Project;
    $relation = $model->members();

    expect($relation)->toBeInstanceOf(BelongsToMany::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
});

it('has many project members', function () {
    $model = new Project;
    $relation = $model->projectMembers();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(ProjectMember::class);
});

it('has many project songs', function () {
    $model = new Project;
    $relation = $model->projectSongs();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(ProjectSong::class);
});

it('belongs to many songs', function () {
    $model = new Project;
    $relation = $model->songs();

    expect($relation)->toBeInstanceOf(BelongsToMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Song::class);
});

it('has many requests', function () {
    $model = new Project;
    $relation = $model->requests();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Request::class);
});

it('has many audience profiles', function () {
    $model = new Project;
    $relation = $model->audienceProfiles();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(AudienceProfile::class);
});

it('has many charts', function () {
    $model = new Project;
    $relation = $model->charts();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Chart::class);
});

it('has many setlists', function () {
    $model = new Project;
    $relation = $model->setlists();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Setlist::class);
});

it('has many setlist share links', function () {
    $model = new Project;
    $relation = $model->setlistShareLinks();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(SetlistShareLink::class);
});

it('has many performance sessions', function () {
    $model = new Project;
    $relation = $model->performanceSessions();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(PerformanceSession::class);
});

it('returns null for performer profile image url when no path', function () {
    $model = new Project;
    $model->performer_profile_image_path = null;

    expect($model->performer_profile_image_url)->toBeNull();
});
