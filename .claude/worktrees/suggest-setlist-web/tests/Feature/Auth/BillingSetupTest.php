<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\BillingPlanCatalog;

it('considers a free user as billing-complete', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
        'billing_activated_at' => now(),
    ]);

    expect($user->isBillingSetupComplete())->toBeTrue();
});

it('allows a free user to access the dashboard without billing redirect', function () {
    $user = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
        'billing_activated_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

it('does not include free plan in billing setup selection groups', function () {
    $catalog = app(BillingPlanCatalog::class);
    $groups = $catalog->selectionGroups();

    $tierKeys = array_column($groups, 'key');

    expect($tierKeys)->not->toContain('free');
});
