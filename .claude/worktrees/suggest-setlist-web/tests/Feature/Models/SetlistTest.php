<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistShareLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

it('belongs to a project', function () {
    $model = new Setlist;
    $relation = $model->project();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
});

it('has many sets', function () {
    $model = new Setlist;
    $relation = $model->sets();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(SetlistSet::class);
});

it('has many share links', function () {
    $model = new Setlist;
    $relation = $model->shareLinks();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(SetlistShareLink::class);
});

// --- Booted hook ---

it('auto-fills user_id from project owner on creation', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    $setlist = Setlist::factory()->create([
        'project_id' => $project->id,
        'user_id' => null,
    ]);

    expect($setlist->user_id)->toBe($owner->id);
});

it('preserves explicit user_id when provided', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    $setlist = Setlist::factory()->create([
        'project_id' => $project->id,
        'user_id' => $member->id,
    ]);

    expect($setlist->user_id)->toBe($member->id);
});

// --- Scopes ---

it('filters active setlists', function () {
    $project = Project::factory()->create();

    Setlist::factory()->create(['project_id' => $project->id, 'archived_at' => null]);
    Setlist::factory()->create(['project_id' => $project->id, 'archived_at' => now()]);

    $active = Setlist::query()->where('project_id', $project->id)->active()->get();

    expect($active)->toHaveCount(1);
    expect($active->first()->archived_at)->toBeNull();
});

it('filters archived setlists', function () {
    $project = Project::factory()->create();

    Setlist::factory()->create(['project_id' => $project->id, 'archived_at' => null]);
    Setlist::factory()->create(['project_id' => $project->id, 'archived_at' => now()]);

    $archived = Setlist::query()->where('project_id', $project->id)->archived()->get();

    expect($archived)->toHaveCount(1);
    expect($archived->first()->archived_at)->not->toBeNull();
});

// --- Casts ---

it('casts generation_meta as array', function () {
    $setlist = Setlist::factory()->create([
        'generation_meta' => ['provider' => 'claude', 'model' => 'opus'],
    ]);

    $setlist->refresh();
    expect($setlist->generation_meta)->toBeArray();
    expect($setlist->generation_meta['provider'])->toBe('claude');
});

it('casts archived_at as datetime', function () {
    $setlist = Setlist::factory()->create([
        'archived_at' => '2026-03-15 10:00:00',
    ]);

    expect($setlist->refresh()->archived_at)->toBeInstanceOf(Carbon::class);
});
