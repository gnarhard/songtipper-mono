<?php

declare(strict_types=1);

use App\Models\AccountUsageAiOperationKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

it('belongs to a user', function () {
    $model = new AccountUsageAiOperationKey;
    $relation = $model->user();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
});

it('casts happened_at as datetime', function () {
    $record = AccountUsageAiOperationKey::factory()->create([
        'happened_at' => '2026-03-15 10:30:00',
    ]);

    expect($record->refresh()->happened_at)->toBeInstanceOf(Carbon::class);
});
