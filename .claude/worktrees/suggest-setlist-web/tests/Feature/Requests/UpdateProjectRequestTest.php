<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    Sanctum::actingAs($this->owner);
});

it('rejects quick tip amounts that are not whole dollar values', function () {
    $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
        'quick_tip_amounts_cents' => [2050, 1000, 500],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['quick_tip_amounts_cents']);
});
