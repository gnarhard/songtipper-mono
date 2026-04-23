<?php

declare(strict_types=1);

use App\Models\Setlist;
use App\Models\SetlistShareAcceptance;
use App\Models\SetlistShareLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('belongs to a share link', function () {
    $model = new SetlistShareAcceptance;
    $relation = $model->shareLink();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(SetlistShareLink::class);
    expect($relation->getForeignKeyName())->toBe('setlist_share_link_id');
});

it('belongs to a user', function () {
    $model = new SetlistShareAcceptance;
    $relation = $model->user();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
});

it('belongs to a copied setlist', function () {
    $model = new SetlistShareAcceptance;
    $relation = $model->copiedSetlist();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Setlist::class);
    expect($relation->getForeignKeyName())->toBe('copied_setlist_id');
});
