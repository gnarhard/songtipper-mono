<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\ChartPageUserPref;
use App\Models\Project;
use App\Models\Song;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $this->song = Song::factory()->create();
    $this->chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
    ]);

    Sanctum::actingAs($this->owner);
});

// --- Show tests ---

it('returns default viewport when no pref exists', function () {
    $response = $this->getJson("/api/v1/me/charts/{$this->chart->id}/pages/0/viewport");

    $data = $response->json('data');
    $response->assertOk();
    expect($data['chart_id'])->toBe($this->chart->id);
    expect($data['page_number'])->toBe(0);
    expect((float) $data['zoom_scale'])->toBe(1.0);
    expect((float) $data['offset_dx'])->toBe(0.0);
    expect((float) $data['offset_dy'])->toBe(0.0);
    expect($data['updated_at'])->toBeNull();
});

it('returns saved viewport pref when one exists', function () {
    $pref = ChartPageUserPref::factory()->create([
        'chart_id' => $this->chart->id,
        'owner_user_id' => $this->owner->id,
        'page_number' => 2,
        'zoom_scale' => 2.5,
        'offset_dx' => 10.0,
        'offset_dy' => -5.0,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$this->chart->id}/pages/2/viewport");

    $data = $response->json('data');
    $response->assertOk();
    expect($data['chart_id'])->toBe($this->chart->id);
    expect($data['page_number'])->toBe(2);
    expect((float) $data['zoom_scale'])->toBe(2.5);
    expect((float) $data['offset_dx'])->toBe(10.0);
    expect((float) $data['offset_dy'])->toBe(-5.0);
    expect($data['updated_at'])->toBe($pref->updated_at->toIso8601String());
});

it('clamps negative page number to 0 on show', function () {
    $response = $this->getJson("/api/v1/me/charts/{$this->chart->id}/pages/-5/viewport");

    $response->assertOk()
        ->assertJsonPath('data.page_number', 0);
});

it('returns 404 when user has no access to chart project on show', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->create(['owner_user_id' => $otherUser->id]);
    $otherChart = Chart::factory()->create([
        'owner_user_id' => $otherUser->id,
        'project_id' => $otherProject->id,
        'song_id' => $this->song->id,
    ]);

    $response = $this->getJson("/api/v1/me/charts/{$otherChart->id}/pages/0/viewport");

    $response->assertNotFound();
});

// --- Update tests ---

it('creates a new viewport pref on update', function () {
    $response = $this->putJson("/api/v1/me/charts/{$this->chart->id}/pages/1/viewport", [
        'zoom_scale' => 1.5,
        'offset_dx' => 20.0,
        'offset_dy' => -10.0,
    ]);

    $data = $response->json('data');
    $response->assertOk();
    expect($data['chart_id'])->toBe($this->chart->id);
    expect($data['page_number'])->toBe(1);
    expect((float) $data['zoom_scale'])->toBe(1.5);
    expect((float) $data['offset_dx'])->toBe(20.0);
    expect((float) $data['offset_dy'])->toBe(-10.0);

    $this->assertDatabaseHas('chart_page_user_prefs', [
        'chart_id' => $this->chart->id,
        'owner_user_id' => $this->owner->id,
        'page_number' => 1,
    ]);
});

it('updates an existing viewport pref', function () {
    ChartPageUserPref::factory()->create([
        'chart_id' => $this->chart->id,
        'owner_user_id' => $this->owner->id,
        'page_number' => 0,
        'zoom_scale' => 1.0,
        'offset_dx' => 0.0,
        'offset_dy' => 0.0,
    ]);

    $response = $this->putJson("/api/v1/me/charts/{$this->chart->id}/pages/0/viewport", [
        'zoom_scale' => 3.0,
        'offset_dx' => 50.0,
        'offset_dy' => 25.0,
    ]);

    $data = $response->json('data');
    $response->assertOk();
    expect((float) $data['zoom_scale'])->toBe(3.0);
    expect((float) $data['offset_dx'])->toBe(50.0);
    expect((float) $data['offset_dy'])->toBe(25.0);

    expect(ChartPageUserPref::where('chart_id', $this->chart->id)
        ->where('owner_user_id', $this->owner->id)
        ->where('page_number', 0)
        ->count())->toBe(1);
});

it('returns 404 when user has no access to chart project on update', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->create(['owner_user_id' => $otherUser->id]);
    $otherChart = Chart::factory()->create([
        'owner_user_id' => $otherUser->id,
        'project_id' => $otherProject->id,
        'song_id' => $this->song->id,
    ]);

    $response = $this->putJson("/api/v1/me/charts/{$otherChart->id}/pages/0/viewport", [
        'zoom_scale' => 1.5,
        'offset_dx' => 0.0,
        'offset_dy' => 0.0,
    ]);

    $response->assertNotFound();
});

// --- Validation tests for UpdateChartPageViewportRequest ---

it('requires all viewport fields', function () {
    $response = $this->putJson("/api/v1/me/charts/{$this->chart->id}/pages/0/viewport", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['zoom_scale', 'offset_dx', 'offset_dy']);
});

it('rejects zoom_scale below minimum', function () {
    $response = $this->putJson("/api/v1/me/charts/{$this->chart->id}/pages/0/viewport", [
        'zoom_scale' => 0.1,
        'offset_dx' => 0,
        'offset_dy' => 0,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['zoom_scale']);
});

it('rejects zoom_scale above maximum', function () {
    $response = $this->putJson("/api/v1/me/charts/{$this->chart->id}/pages/0/viewport", [
        'zoom_scale' => 5.0,
        'offset_dx' => 0,
        'offset_dy' => 0,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['zoom_scale']);
});

it('rejects non-numeric offset values', function () {
    $response = $this->putJson("/api/v1/me/charts/{$this->chart->id}/pages/0/viewport", [
        'zoom_scale' => 1.0,
        'offset_dx' => 'abc',
        'offset_dy' => 'xyz',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['offset_dx', 'offset_dy']);
});

it('accepts boundary zoom_scale values', function () {
    $response = $this->putJson("/api/v1/me/charts/{$this->chart->id}/pages/0/viewport", [
        'zoom_scale' => 0.5,
        'offset_dx' => 0,
        'offset_dy' => 0,
    ]);

    $response->assertOk();

    $response = $this->putJson("/api/v1/me/charts/{$this->chart->id}/pages/0/viewport", [
        'zoom_scale' => 4.0,
        'offset_dx' => 0,
        'offset_dy' => 0,
    ]);

    $response->assertOk();
});

// --- Member access test ---

it('allows project members to access chart viewport', function () {
    $member = User::factory()->create();
    $this->project->addMember($member);

    Sanctum::actingAs($member);

    $response = $this->getJson("/api/v1/me/charts/{$this->chart->id}/pages/0/viewport");

    $response->assertOk();
});
