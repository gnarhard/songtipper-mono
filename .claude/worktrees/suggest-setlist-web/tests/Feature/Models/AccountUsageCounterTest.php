<?php

declare(strict_types=1);

use App\Models\AccountUsageCounter;
use App\Models\AccountUsageFlag;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

it('belongs to a user', function () {
    $model = new AccountUsageCounter;
    $relation = $model->user();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(User::class);
});

it('has many flags', function () {
    $model = new AccountUsageCounter;
    $relation = $model->flags();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(AccountUsageFlag::class);
});

it('uses user_id as the foreign key for flags', function () {
    $model = new AccountUsageCounter;
    $relation = $model->flags();

    expect($relation->getForeignKeyName())->toBe('user_id');
    expect($relation->getLocalKeyName())->toBe('user_id');
});

it('casts integer fields correctly', function () {
    $counter = AccountUsageCounter::factory()->create([
        'storage_bytes' => 1024,
        'lifetime_ai_operations' => 50,
    ]);

    $counter->refresh();
    expect($counter->storage_bytes)->toBeInt();
    expect($counter->lifetime_ai_operations)->toBeInt();
});

it('casts warning_markers as array', function () {
    $counter = AccountUsageCounter::factory()->create([
        'warning_markers' => ['storage_high', 'ai_quota_near'],
    ]);

    $counter->refresh();
    expect($counter->warning_markers)->toBeArray();
    expect($counter->warning_markers)->toContain('storage_high');
});

it('casts datetime fields correctly', function () {
    $counter = AccountUsageCounter::factory()->create([
        'last_activity_at' => '2026-03-15 10:00:00',
    ]);

    $counter->refresh();
    expect($counter->last_activity_at)->toBeInstanceOf(Carbon::class);
});
