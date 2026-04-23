<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('allows any owner to exceed two hundred repertoire songs since limits are unlimited', function () {
    $owner = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
    ]);
    fillProjectRepertoire($project, 200);
    $newSong = Song::factory()->create();

    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/v1/me/projects/{$project->id}/repertoire", [
        'song_id' => $newSong->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Song added to repertoire.');

    expect(ProjectSong::query()->where('project_id', $project->id)->count())->toBe(201);
});

it('allows pro owners to exceed two hundred repertoire songs', function () {
    $owner = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
    ]);
    fillProjectRepertoire($project, 200);
    $newSong = Song::factory()->create();

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/me/projects/{$project->id}/repertoire", [
        'song_id' => $newSong->id,
    ])->assertCreated()
        ->assertJsonPath('message', 'Song added to repertoire.');

    expect(ProjectSong::query()->where('project_id', $project->id)->count())->toBe(201);
});

it('imports all bulk import rows when repertoire is unlimited', function () {
    $owner = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_FREE,
        'billing_status' => User::BILLING_STATUS_EARNING,
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
    ]);
    fillProjectRepertoire($project, 199);

    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/v1/me/projects/{$project->id}/repertoire/bulk-import/confirm", [
        'songs' => [
            [
                'title' => 'Song Two Hundred',
                'artist' => 'Cap Test',
            ],
            [
                'title' => 'Song Two Hundred One',
                'artist' => 'Cap Test',
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.imported', 2)
        ->assertJsonPath('data.duplicates', 0)
        ->assertJsonPath('data.limit_reached', 0);

    expect(ProjectSong::query()->where('project_id', $project->id)->count())->toBe(201);
});

it('allows copying repertoire between projects since limits are unlimited', function () {
    $owner = billingReadyUser([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
    ]);
    $sourceProject = Project::factory()->create([
        'owner_user_id' => $owner->id,
    ]);
    $destinationProject = Project::factory()->create([
        'owner_user_id' => $owner->id,
    ]);
    $sourceProjectSong = ProjectSong::factory()->create([
        'project_id' => $sourceProject->id,
    ]);
    fillProjectRepertoire($destinationProject, 200);

    Sanctum::actingAs($owner);

    $response = $this->postJson(
        "/api/v1/me/projects/{$destinationProject->id}/repertoire/copy-from",
        [
            'source_project_id' => $sourceProject->id,
            'source_project_song_ids' => [$sourceProjectSong->id],
            'include_charts' => false,
        ],
    );

    $response->assertSuccessful();

    expect(ProjectSong::query()->where('project_id', $destinationProject->id)->count())->toBe(201);
});

function fillProjectRepertoire(Project $project, int $count): void
{
    ProjectSong::factory()->count($count)->create([
        'project_id' => $project->id,
    ]);
}
