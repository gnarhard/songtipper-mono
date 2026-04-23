<?php

declare(strict_types=1);

use App\Models\AudienceProfile;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('belongs to a user', function () {
    $model = new IdempotencyKey;
    $relation = $model->user();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
});

it('belongs to an audience profile', function () {
    $model = new IdempotencyKey;
    $relation = $model->audienceProfile();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(AudienceProfile::class);
});

it('casts status_code as integer', function () {
    $key = IdempotencyKey::factory()->create([
        'status_code' => 200,
    ]);

    expect($key->refresh()->status_code)->toBeInt();
});

it('casts response_json as array', function () {
    $key = IdempotencyKey::factory()->create([
        'response_json' => ['data' => ['id' => 1]],
    ]);

    $key->refresh();
    expect($key->response_json)->toBeArray();
    expect($key->response_json['data']['id'])->toBe(1);
});
