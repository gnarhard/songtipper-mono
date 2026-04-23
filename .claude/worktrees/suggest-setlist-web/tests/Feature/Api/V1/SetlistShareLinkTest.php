<?php

declare(strict_types=1);

use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistShareAcceptance;
use App\Models\SetlistShareLink;
use App\Models\SetlistSong;
use App\Models\Song;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->readonlyMember = User::factory()->create();
    $this->outsider = User::factory()->create();

    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);

    $this->project->addMember($this->member, ProjectMemberRole::Member);
    $this->project->addMember($this->readonlyMember, ProjectMemberRole::Readonly);
});

function sharedSourceSetlist(Project $project): Setlist
{
    $setlist = Setlist::factory()->create([
        'project_id' => $project->id,
        'name' => 'Friday Night',
        'notes' => 'Watch the crowd before the encore.',
    ]);

    $firstSet = SetlistSet::factory()->create([
        'setlist_id' => $setlist->id,
        'name' => 'Set 1',
        'order_index' => 0,
    ]);
    $secondSet = SetlistSet::factory()->create([
        'setlist_id' => $setlist->id,
        'name' => 'Encore',
        'order_index' => 1,
    ]);

    $songOne = Song::factory()->create([
        'title' => 'Dreams',
        'artist' => 'Fleetwood Mac',
    ]);
    $songTwo = Song::factory()->create([
        'title' => 'Africa',
        'artist' => 'Toto',
    ]);

    $projectSongOne = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $songOne->id,
        'notes' => 'Start with the mellow intro voicing.',
    ]);
    $projectSongTwo = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $songTwo->id,
    ]);

    SetlistSong::factory()->create([
        'set_id' => $firstSet->id,
        'project_song_id' => $projectSongOne->id,
        'order_index' => 0,
        'notes' => null,
        'color_hex' => '#112233',
    ]);
    SetlistSong::factory()->create([
        'set_id' => $firstSet->id,
        'project_song_id' => null,
        'order_index' => 1,
        'notes' => 'Short tuning break.',
        'color_hex' => null,
    ]);
    SetlistSong::factory()->create([
        'set_id' => $secondSet->id,
        'project_song_id' => $projectSongTwo->id,
        'order_index' => 0,
        'notes' => 'Big finish',
        'color_hex' => '#445566',
    ]);

    return $setlist->fresh(['sets.songs.projectSong.song']);
}

it('allows owners and members to create a setlist share link', function (string $actorKey) {
    $setlist = sharedSourceSetlist($this->project);
    $actor = $this->{$actorKey};
    Sanctum::actingAs($actor);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}/share-link",
        [],
        ['Idempotency-Key' => "share-link-{$actor->id}"],
    );

    $response->assertCreated()
        ->assertJsonPath('data.project_id', $this->project->id)
        ->assertJsonPath('data.setlist_id', $setlist->id);

    expect($response->json('data.deep_link_url'))->toContain('/shared-setlists/');
    expect($response->json('data.share_url'))->toContain('/shared-setlists/');

    $this->assertDatabaseHas('setlist_share_links', [
        'project_id' => $this->project->id,
        'setlist_id' => $setlist->id,
    ]);
})->with([
    'owner' => 'owner',
    'member' => 'member',
]);

it('forbids readonly members from creating share links', function () {
    $setlist = sharedSourceSetlist($this->project);
    Sanctum::actingAs($this->readonlyMember);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}/share-link",
    )->assertForbidden();

    $this->assertDatabaseMissing('setlist_share_links', [
        'project_id' => $this->project->id,
        'setlist_id' => $setlist->id,
    ]);
});

it('copies a shared setlist for the accepting user inside the owning project', function () {
    $sourceSetlist = sharedSourceSetlist($this->project);
    $shareLink = SetlistShareLink::query()->create([
        'project_id' => $this->project->id,
        'setlist_id' => $sourceSetlist->id,
        'created_by_user_id' => $this->owner->id,
        'token' => 'shared-setlist-token',
    ]);

    Sanctum::actingAs($this->member);

    $response = $this->postJson(
        "/api/v1/me/shared-setlists/{$shareLink->token}/accept",
        [],
        ['Idempotency-Key' => 'accept-shared-setlist'],
    );

    $response->assertStatus(201)
        ->assertJsonPath('data.project.id', $this->project->id)
        ->assertJsonPath('data.setlist.project_id', $this->project->id)
        ->assertJsonPath('data.setlist.name', 'Friday Night (Copy)')
        ->assertJsonPath('data.setlist.notes', 'Watch the crowd before the encore.')
        ->assertJsonPath('data.setlist.sets.0.name', 'Set 1')
        ->assertJsonPath('data.setlist.sets.0.songs.0.song.title', 'Dreams')
        ->assertJsonPath('data.setlist.sets.0.songs.0.color_hex', '#112233')
        ->assertJsonPath('data.setlist.sets.0.songs.1.project_song_id', null)
        ->assertJsonPath('data.setlist.sets.0.songs.1.notes', 'Short tuning break.')
        ->assertJsonPath('data.setlist.sets.1.name', 'Encore')
        ->assertJsonPath('data.setlist.sets.1.songs.0.song.title', 'Africa')
        ->assertJsonPath('data.was_already_accepted', false);

    expect(Setlist::query()->where('project_id', $this->project->id)->count())->toBe(2);

    $copiedSetlistId = (int) $response->json('data.setlist.id');
    expect($copiedSetlistId)->not()->toBe($sourceSetlist->id);

    $this->assertDatabaseHas('setlist_share_acceptances', [
        'setlist_share_link_id' => $shareLink->id,
        'user_id' => $this->member->id,
        'copied_setlist_id' => $copiedSetlistId,
    ]);
});

it('auto-adds shared songs that are missing from the destination project repertoire', function () {
    $sourceSetlist = Setlist::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'Shared Import',
    ]);
    $set = SetlistSet::factory()->create([
        'setlist_id' => $sourceSetlist->id,
        'name' => 'Set 1',
        'order_index' => 0,
    ]);

    $song = Song::factory()->create([
        'title' => 'Landslide',
        'artist' => 'Fleetwood Mac',
    ]);
    $foreignProject = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $foreignProjectSong = ProjectSong::factory()->create([
        'project_id' => $foreignProject->id,
        'song_id' => $song->id,
        'notes' => 'Keep the walk-up before the chorus.',
    ]);

    SetlistSong::factory()->create([
        'set_id' => $set->id,
        'project_song_id' => $foreignProjectSong->id,
        'order_index' => 0,
        'notes' => null,
        'color_hex' => '#778899',
    ]);

    $shareLink = SetlistShareLink::query()->create([
        'project_id' => $this->project->id,
        'setlist_id' => $sourceSetlist->id,
        'created_by_user_id' => $this->owner->id,
        'token' => 'shared-setlist-missing-song-token',
    ]);

    Sanctum::actingAs($this->member);

    $response = $this->postJson(
        "/api/v1/me/shared-setlists/{$shareLink->token}/accept",
        [],
        ['Idempotency-Key' => 'accept-shared-setlist-missing-song'],
    );

    $createdProjectSong = ProjectSong::query()
        ->where('project_id', $this->project->id)
        ->where('song_id', $song->id)
        ->first();

    expect($createdProjectSong)->not()->toBeNull();
    expect($createdProjectSong?->id)->not()->toBe($foreignProjectSong->id);
    expect($createdProjectSong?->notes)->toBe('Keep the walk-up before the chorus.');
    expect(
        ProjectSong::query()
            ->where('project_id', $this->project->id)
            ->where('song_id', $song->id)
            ->count()
    )->toBe(1);

    $response->assertCreated()
        ->assertJsonPath('data.project.id', $this->project->id)
        ->assertJsonPath('data.setlist.project_id', $this->project->id)
        ->assertJsonPath('data.setlist.sets.0.songs.0.song.title', 'Landslide')
        ->assertJsonPath('data.setlist.sets.0.songs.0.project_song_id', $createdProjectSong?->id)
        ->assertJsonPath('data.setlist.sets.0.songs.0.color_hex', '#778899');
});

it('reuses the same accepted copy when the same user opens the link again', function () {
    $sourceSetlist = sharedSourceSetlist($this->project);
    $shareLink = SetlistShareLink::query()->create([
        'project_id' => $this->project->id,
        'setlist_id' => $sourceSetlist->id,
        'created_by_user_id' => $this->owner->id,
        'token' => 'repeat-shared-setlist-token',
    ]);

    Sanctum::actingAs($this->member);

    $first = $this->postJson("/api/v1/me/shared-setlists/{$shareLink->token}/accept");
    $second = $this->postJson("/api/v1/me/shared-setlists/{$shareLink->token}/accept");

    $first->assertStatus(201)
        ->assertJsonPath('data.was_already_accepted', false);
    $second->assertOk()
        ->assertJsonPath('data.was_already_accepted', true)
        ->assertJsonPath('data.setlist.id', $first->json('data.setlist.id'));

    expect(SetlistShareAcceptance::query()->count())->toBe(1);
    expect(Setlist::query()->where('project_id', $this->project->id)->count())->toBe(2);
});

it('returns 404 when creating a share link for a project the user cannot access', function () {
    $setlist = sharedSourceSetlist($this->project);
    Sanctum::actingAs($this->outsider);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}/share-link",
    )->assertNotFound();
});

it('returns 404 when creating a share link for a setlist from a different project', function () {
    $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
    $otherSetlist = sharedSourceSetlist($otherProject);
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/{$otherSetlist->id}/share-link",
    )->assertNotFound();
});

it('returns 404 when the accepting user does not have access to the owning project', function () {
    $sourceSetlist = sharedSourceSetlist($this->project);
    $shareLink = SetlistShareLink::query()->create([
        'project_id' => $this->project->id,
        'setlist_id' => $sourceSetlist->id,
        'created_by_user_id' => $this->owner->id,
        'token' => 'private-shared-setlist-token',
    ]);

    Sanctum::actingAs($this->outsider);

    $this->postJson("/api/v1/me/shared-setlists/{$shareLink->token}/accept")
        ->assertNotFound();

    expect(SetlistShareAcceptance::query()->count())->toBe(0);
    expect(Setlist::query()->where('project_id', $this->project->id)->count())->toBe(1);
});
