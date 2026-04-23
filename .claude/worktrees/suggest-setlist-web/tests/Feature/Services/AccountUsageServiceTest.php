<?php

declare(strict_types=1);

use App\Models\AccountUsageCounter;
use App\Models\Project;
use App\Services\AccountUsageService;
use Illuminate\Support\Facades\Storage;

it('returns usage payload for user', function () {
    $user = billingReadyUser();

    $service = app(AccountUsageService::class);
    $result = $service->usagePayload($user);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['storage', 'ai', 'plan']);
});

it('touches user activity', function () {
    $user = billingReadyUser();

    $service = app(AccountUsageService::class);
    $counter = $service->touchUserActivity($user);

    expect($counter)->toBeInstanceOf(AccountUsageCounter::class)
        ->and($counter->last_activity_at)->not->toBeNull();
});

it('touches project activity through owner', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $service = app(AccountUsageService::class);
    $counter = $service->touchProjectActivity($project);

    expect($counter->user_id)->toBe($user->id);
});

it('increments storage bytes', function () {
    $user = billingReadyUser();

    $service = app(AccountUsageService::class);
    $service->incrementStorageBytes($user, 1024, 'chart_pdf_bytes');

    $counter = AccountUsageCounter::query()->where('user_id', $user->id)->first();
    expect((int) $counter->storage_bytes)->toBeGreaterThanOrEqual(1024)
        ->and((int) $counter->chart_pdf_bytes)->toBeGreaterThanOrEqual(1024);
});

it('does not increment storage for zero bytes', function () {
    $user = billingReadyUser();

    $service = app(AccountUsageService::class);
    $service->incrementStorageBytes($user, 0, 'chart_pdf_bytes');

    $counter = AccountUsageCounter::query()->where('user_id', $user->id)->first();
    // Counter may or may not exist, but if it does, storage should be 0
    expect($counter?->storage_bytes ?? 0)->toBe(0);
});

it('returns null storage limit response when under limit', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $service = app(AccountUsageService::class);
    $result = $service->storageLimitResponse($project, 100);

    expect($result)->toBeNull();
});

it('records AI operation', function () {
    $user = billingReadyUser();

    $service = app(AccountUsageService::class);
    $result = $service->recordAiOperation($user, 'gemini', 'enrichment');

    expect($result)->toBeTrue();
});

it('records AI operation with operation key deduplication', function () {
    $user = billingReadyUser();

    $service = app(AccountUsageService::class);
    $result1 = $service->recordAiOperation($user, 'gemini', 'enrichment', 'unique-key-123');
    $result2 = $service->recordAiOperation($user, 'gemini', 'enrichment', 'unique-key-123');

    expect($result1)->toBeTrue()
        ->and($result2)->toBeFalse();
});

it('syncs storage usage', function () {
    $user = billingReadyUser();
    Storage::fake('public');

    $service = app(AccountUsageService::class);
    $counter = $service->syncStorageUsage($user);

    expect($counter)->toBeInstanceOf(AccountUsageCounter::class);
});

it('reserves bulk AI allowance', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $service = app(AccountUsageService::class);
    $result = $service->reserveBulkAiAllowance($project, 5);

    expect($result)->toHaveKeys(['allowed', 'deferred', 'limit_response'])
        ->and($result['allowed'])->toBeGreaterThanOrEqual(0);
});

it('returns zero allowance for zero operations', function () {
    $user = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    $service = app(AccountUsageService::class);
    $result = $service->reserveBulkAiAllowance($project, 0);

    expect($result['allowed'])->toBe(0)
        ->and($result['deferred'])->toBe(0)
        ->and($result['limit_response'])->toBeNull();
});
