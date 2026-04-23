<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use App\Models\UserPayoutAccount;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-03-14 18:00:00+00:00');

    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    UserPayoutAccount::query()->create([
        'user_id' => $this->owner->id,
        'stripe_account_id' => 'acct_stats_test',
        'status' => UserPayoutAccount::STATUS_ENABLED,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);
    Sanctum::actingAs($this->owner);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns null for invalid preset in after validation', function () {
    // When the preset value is not a valid enum, after() should return early
    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/stats?timezone=America/Denver&preset=invalid_value"
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['preset']);
});
