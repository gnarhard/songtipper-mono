<?php

declare(strict_types=1);

use App\Models\AccountUsageDailyRollup;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

it('belongs to a user', function () {
    $model = new AccountUsageDailyRollup;
    $relation = $model->user();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
});

it('casts rollup_date as date', function () {
    $rollup = AccountUsageDailyRollup::factory()->create([
        'rollup_date' => '2026-03-15',
    ]);

    $rollup->refresh();
    expect($rollup->rollup_date)->toBeInstanceOf(Carbon::class);
});

it('casts integer fields correctly', function () {
    $rollup = AccountUsageDailyRollup::factory()->create([
        'ai_operations' => 10,
        'queue_failures' => 2,
    ]);

    $rollup->refresh();
    expect($rollup->ai_operations)->toBeInt();
    expect($rollup->queue_failures)->toBeInt();
});
