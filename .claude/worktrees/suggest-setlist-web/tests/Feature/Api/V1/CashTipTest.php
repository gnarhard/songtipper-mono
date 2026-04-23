<?php

declare(strict_types=1);

use App\Models\CashTip;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
});

// --- Store ---

it('stores a cash tip', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/cash-tips", [
        'amount_cents' => 2500,
        'local_date' => '2026-03-15',
        'timezone' => 'America/Denver',
        'note' => 'Wedding gig',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.amount_cents', 2500)
        ->assertJsonPath('data.local_date', '2026-03-15')
        ->assertJsonPath('data.timezone', 'America/Denver')
        ->assertJsonPath('data.note', 'Wedding gig')
        ->assertJsonPath('data.project_id', $this->project->id);

    $this->assertDatabaseHas('cash_tips', [
        'project_id' => $this->project->id,
        'amount_cents' => 2500,
        'local_date' => '2026-03-15',
    ]);
});

it('stores a cash tip without a note', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/cash-tips", [
        'amount_cents' => 1000,
        'local_date' => '2026-03-15',
        'timezone' => 'UTC',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.note', null);
});

it('trims empty note to null', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/cash-tips", [
        'amount_cents' => 500,
        'local_date' => '2026-03-15',
        'timezone' => 'UTC',
        'note' => '   ',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.note', null);
});

it('rejects zero amount', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/cash-tips", [
        'amount_cents' => 0,
        'local_date' => '2026-03-15',
        'timezone' => 'UTC',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount_cents']);
});

it('rejects missing local_date', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/cash-tips", [
        'amount_cents' => 1000,
        'timezone' => 'UTC',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['local_date']);
});

it('rejects invalid timezone', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/cash-tips", [
        'amount_cents' => 1000,
        'local_date' => '2026-03-15',
        'timezone' => 'Invalid/Zone',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['timezone']);
});

it('returns 404 for non-member', function () {
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/cash-tips", [
        'amount_cents' => 1000,
        'local_date' => '2026-03-15',
        'timezone' => 'UTC',
    ]);

    $response->assertNotFound();
});

// --- Update ---

it('updates a cash tip', function () {
    Sanctum::actingAs($this->owner);

    $cashTip = CashTip::factory()->create([
        'project_id' => $this->project->id,
        'amount_cents' => 1000,
        'local_date' => '2026-03-10',
        'timezone' => 'UTC',
        'note' => 'Old note',
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/cash-tips/{$cashTip->id}",
        [
            'amount_cents' => 2500,
            'local_date' => '2026-03-15',
            'timezone' => 'America/Denver',
            'note' => 'Updated note',
        ]
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.amount_cents', 2500)
        ->assertJsonPath('data.local_date', '2026-03-15')
        ->assertJsonPath('data.timezone', 'America/Denver')
        ->assertJsonPath('data.note', 'Updated note');

    $this->assertDatabaseHas('cash_tips', [
        'id' => $cashTip->id,
        'amount_cents' => 2500,
        'local_date' => '2026-03-15',
    ]);
});

it('trims empty note to null on update', function () {
    Sanctum::actingAs($this->owner);

    $cashTip = CashTip::factory()->create([
        'project_id' => $this->project->id,
        'note' => 'Has a note',
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/cash-tips/{$cashTip->id}",
        [
            'amount_cents' => $cashTip->amount_cents,
            'local_date' => $cashTip->local_date->format('Y-m-d'),
            'timezone' => $cashTip->timezone,
            'note' => '   ',
        ]
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.note', null);
});

it('returns 404 when updating a cash tip from another project', function () {
    Sanctum::actingAs($this->owner);

    $otherProject = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $cashTip = CashTip::factory()->create([
        'project_id' => $otherProject->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/cash-tips/{$cashTip->id}",
        [
            'amount_cents' => 500,
            'local_date' => '2026-03-15',
            'timezone' => 'UTC',
        ]
    );

    $response->assertNotFound();
});

it('returns 404 when non-member updates a cash tip', function () {
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger);

    $cashTip = CashTip::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/cash-tips/{$cashTip->id}",
        [
            'amount_cents' => 500,
            'local_date' => '2026-03-15',
            'timezone' => 'UTC',
        ]
    );

    $response->assertNotFound();
});

it('rejects invalid data on update', function () {
    Sanctum::actingAs($this->owner);

    $cashTip = CashTip::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/cash-tips/{$cashTip->id}",
        [
            'amount_cents' => 0,
            'local_date' => 'not-a-date',
            'timezone' => 'Invalid/Zone',
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount_cents', 'local_date', 'timezone']);
});

// --- Index ---

it('lists cash tips for a project', function () {
    Sanctum::actingAs($this->owner);

    CashTip::factory()->count(3)->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/cash-tips");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('filters cash tips by local_date', function () {
    Sanctum::actingAs($this->owner);

    CashTip::factory()->forDate('2026-03-15')->create([
        'project_id' => $this->project->id,
    ]);
    CashTip::factory()->forDate('2026-03-14')->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/cash-tips?local_date=2026-03-15"
    );

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.local_date', '2026-03-15');
});

it('does not return cash tips from other projects', function () {
    Sanctum::actingAs($this->owner);

    $otherProject = Project::factory()->create();
    CashTip::factory()->create(['project_id' => $otherProject->id]);
    CashTip::factory()->create(['project_id' => $this->project->id]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/cash-tips");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

// --- Destroy ---

it('deletes a cash tip', function () {
    Sanctum::actingAs($this->owner);

    $cashTip = CashTip::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->deleteJson(
        "/api/v1/me/projects/{$this->project->id}/cash-tips/{$cashTip->id}"
    );

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Cash tip deleted.');

    $this->assertDatabaseMissing('cash_tips', ['id' => $cashTip->id]);
});

it('returns 404 when deleting a cash tip from another project', function () {
    Sanctum::actingAs($this->owner);

    $otherProject = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $cashTip = CashTip::factory()->create([
        'project_id' => $otherProject->id,
    ]);

    $response = $this->deleteJson(
        "/api/v1/me/projects/{$this->project->id}/cash-tips/{$cashTip->id}"
    );

    $response->assertNotFound();
});
