<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistSong;
use App\Models\Song;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
});

describe('Setlist CRUD', function () {
    it('lists setlists for a project', function () {
        Sanctum::actingAs($this->owner);

        $setlist = Setlist::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'Friday Night Set',
        ]);

        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/setlists");

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Friday Night Set');
    });

    it('creates a setlist', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/setlists", [
            'name' => 'Saturday Gig',
            'notes' => 'Bring capo and alternate guitar.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Setlist created')
            ->assertJsonPath('setlist.name', 'Saturday Gig')
            ->assertJsonPath('setlist.notes', 'Bring capo and alternate guitar.');

        $this->assertDatabaseHas('setlists', [
            'project_id' => $this->project->id,
            'name' => 'Saturday Gig',
            'notes' => 'Bring capo and alternate guitar.',
        ]);
    });

    it('shows a setlist with sets and songs', function () {
        Sanctum::actingAs($this->owner);

        $setlist = Setlist::factory()->create(['project_id' => $this->project->id]);
        $set = SetlistSet::factory()->create(['setlist_id' => $setlist->id, 'name' => 'Set 1']);

        $song = Song::factory()->create(['title' => 'Bohemian Rhapsody']);
        $projectSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'song_id' => $song->id,
        ]);
        SetlistSong::factory()->create([
            'set_id' => $set->id,
            'project_song_id' => $projectSong->id,
        ]);

        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}");

        $response->assertSuccessful()
            ->assertJsonPath('setlist.sets.0.name', 'Set 1')
            ->assertJsonPath('setlist.sets.0.songs.0.song.title', 'Bohemian Rhapsody');
    });

    it('updates a setlist', function () {
        Sanctum::actingAs($this->owner);

        $setlist = Setlist::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'Old Name',
        ]);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}", [
            'name' => 'New Name',
            'notes' => 'Updated notes for the show.',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Setlist updated')
            ->assertJsonPath('setlist.name', 'New Name')
            ->assertJsonPath('setlist.notes', 'Updated notes for the show.');

        $this->assertDatabaseHas('setlists', [
            'id' => $setlist->id,
            'name' => 'New Name',
            'notes' => 'Updated notes for the show.',
        ]);
    });

    it('deletes a setlist', function () {
        Sanctum::actingAs($this->owner);

        $setlist = Setlist::factory()->create(['project_id' => $this->project->id]);

        $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}");

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Setlist deleted');

        $this->assertDatabaseMissing('setlists', ['id' => $setlist->id]);
    });

    it('requires authentication', function () {
        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/setlists");

        $response->assertUnauthorized();
    });

    it('prevents access to other users projects', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/setlists");

        $response->assertNotFound();
    });

    it('returns 404 when creating a setlist for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/setlists", [
            'name' => 'Forbidden Setlist',
        ]);

        $response->assertNotFound();
    });

    it('returns 404 when showing a setlist for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $setlist = Setlist::factory()->create(['project_id' => $this->project->id]);

        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}");

        $response->assertNotFound();
    });

    it('returns 404 when updating a setlist for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $setlist = Setlist::factory()->create(['project_id' => $this->project->id]);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}", [
            'name' => 'Should Fail',
        ]);

        $response->assertNotFound();
    });

    it('returns 404 when deleting a setlist for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $setlist = Setlist::factory()->create(['project_id' => $this->project->id]);

        $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}");

        $response->assertNotFound();
    });

    it('returns 404 when showing a setlist that belongs to a different project', function () {
        Sanctum::actingAs($this->owner);

        $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
        $setlist = Setlist::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}");

        $response->assertNotFound();
    });

    it('validates required name field', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/setlists", [
            'name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });
});

describe('SetlistSet CRUD', function () {
    beforeEach(function () {
        $this->setlist = Setlist::factory()->create(['project_id' => $this->project->id]);
    });

    it('creates a set', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets",
            ['name' => 'Set 1']
        );

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Set created')
            ->assertJsonPath('set.name', 'Set 1')
            ->assertJsonPath('set.order_index', 0);

        $this->assertDatabaseHas('setlist_sets', [
            'setlist_id' => $this->setlist->id,
            'name' => 'Set 1',
            'order_index' => 0,
        ]);
    });

    it('auto-names a set when name is omitted', function () {
        Sanctum::actingAs($this->owner);

        SetlistSet::factory()->create([
            'setlist_id' => $this->setlist->id,
            'name' => 'Set 1',
            'order_index' => 0,
        ]);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets",
            []
        );

        $response->assertStatus(201)
            ->assertJsonPath('set.name', 'Set 2')
            ->assertJsonPath('set.order_index', 1);
    });

    it('auto-increments order_index', function () {
        Sanctum::actingAs($this->owner);

        SetlistSet::factory()->create([
            'setlist_id' => $this->setlist->id,
            'order_index' => 0,
        ]);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets",
            ['name' => 'Set 2']
        );

        $response->assertStatus(201)
            ->assertJsonPath('set.order_index', 1);
    });

    it('allows custom order_index', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets",
            ['name' => 'Set 1', 'order_index' => 5]
        );

        $response->assertStatus(201)
            ->assertJsonPath('set.order_index', 5);
    });

    it('updates a set', function () {
        Sanctum::actingAs($this->owner);

        $set = SetlistSet::factory()->create([
            'setlist_id' => $this->setlist->id,
            'name' => 'Old Name',
        ]);

        $response = $this->putJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$set->id}",
            [
                'name' => 'New Name',
                'order_index' => 2,
            ]
        );

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Set updated')
            ->assertJsonPath('set.name', 'New Name')
            ->assertJsonPath('set.order_index', 2);

        $this->assertDatabaseHas('setlist_sets', [
            'id' => $set->id,
            'name' => 'New Name',
            'order_index' => 2,
        ]);
    });

    it('deletes a set', function () {
        Sanctum::actingAs($this->owner);

        $set = SetlistSet::factory()->create(['setlist_id' => $this->setlist->id]);

        $response = $this->deleteJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$set->id}"
        );

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Set deleted');

        $this->assertDatabaseMissing('setlist_sets', ['id' => $set->id]);
    });

    it('re-numbers default set titles after deleting a set from a normal setlist', function () {
        Sanctum::actingAs($this->owner);

        $firstSet = SetlistSet::factory()->create([
            'setlist_id' => $this->setlist->id,
            'name' => 'Set 1',
            'order_index' => 0,
        ]);
        $secondSet = SetlistSet::factory()->create([
            'setlist_id' => $this->setlist->id,
            'name' => 'Set 2',
            'order_index' => 1,
        ]);
        $thirdSet = SetlistSet::factory()->create([
            'setlist_id' => $this->setlist->id,
            'name' => 'Set 3',
            'order_index' => 2,
        ]);

        $response = $this->deleteJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$secondSet->id}"
        );

        $response->assertSuccessful();

        $this->assertDatabaseMissing('setlist_sets', ['id' => $secondSet->id]);
        $this->assertDatabaseHas('setlist_sets', [
            'id' => $firstSet->id,
            'name' => 'Set 1',
            'order_index' => 0,
        ]);
        $this->assertDatabaseHas('setlist_sets', [
            'id' => $thirdSet->id,
            'name' => 'Set 2',
            'order_index' => 1,
        ]);
    });

    it('keeps custom set titles in normal setlists while reindexing order', function () {
        Sanctum::actingAs($this->owner);

        $firstSet = SetlistSet::factory()->create([
            'setlist_id' => $this->setlist->id,
            'name' => 'Set 1',
            'order_index' => 0,
        ]);
        $secondSet = SetlistSet::factory()->create([
            'setlist_id' => $this->setlist->id,
            'name' => 'Acoustic Encore',
            'order_index' => 1,
        ]);
        $thirdSet = SetlistSet::factory()->create([
            'setlist_id' => $this->setlist->id,
            'name' => 'Set 3',
            'order_index' => 2,
        ]);

        $response = $this->deleteJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$firstSet->id}"
        );

        $response->assertSuccessful();

        $this->assertDatabaseHas('setlist_sets', [
            'id' => $secondSet->id,
            'name' => 'Acoustic Encore',
            'order_index' => 0,
        ]);
        $this->assertDatabaseHas('setlist_sets', [
            'id' => $thirdSet->id,
            'name' => 'Set 2',
            'order_index' => 1,
        ]);
    });

    it('renumbers default titles even when setlist has legacy generation metadata', function () {
        Sanctum::actingAs($this->owner);

        $smartSetlist = Setlist::factory()->create([
            'project_id' => $this->project->id,
            'generation_meta' => [
                'seed' => 42,
                'generation_version' => 'smart-v1',
            ],
        ]);

        $firstSet = SetlistSet::factory()->create([
            'setlist_id' => $smartSetlist->id,
            'name' => 'Set 1',
            'order_index' => 0,
        ]);
        $secondSet = SetlistSet::factory()->create([
            'setlist_id' => $smartSetlist->id,
            'name' => 'Acoustic Encore',
            'order_index' => 1,
        ]);
        $thirdSet = SetlistSet::factory()->create([
            'setlist_id' => $smartSetlist->id,
            'name' => 'Set 3',
            'order_index' => 2,
        ]);

        $response = $this->deleteJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$smartSetlist->id}/sets/{$secondSet->id}"
        );

        $response->assertSuccessful();

        $this->assertDatabaseHas('setlist_sets', [
            'id' => $firstSet->id,
            'name' => 'Set 1',
            'order_index' => 0,
        ]);
        $this->assertDatabaseHas('setlist_sets', [
            'id' => $thirdSet->id,
            'name' => 'Set 2',
            'order_index' => 1,
        ]);
    });

    it('returns 404 for set in different setlist', function () {
        Sanctum::actingAs($this->owner);

        $otherSetlist = Setlist::factory()->create(['project_id' => $this->project->id]);
        $set = SetlistSet::factory()->create(['setlist_id' => $otherSetlist->id]);

        $response = $this->deleteJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$set->id}"
        );

        $response->assertNotFound();
    });

    it('returns 404 when creating a set for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets",
            ['name' => 'Blocked Set']
        );

        $response->assertNotFound();
    });

    it('returns 404 when updating a set for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $set = SetlistSet::factory()->create(['setlist_id' => $this->setlist->id]);

        $response = $this->putJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$set->id}",
            ['name' => 'Blocked']
        );

        $response->assertNotFound();
    });

    it('returns 404 when deleting a set for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $set = SetlistSet::factory()->create(['setlist_id' => $this->setlist->id]);

        $response = $this->deleteJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$set->id}"
        );

        $response->assertNotFound();
    });

    it('returns 404 when creating a set for a setlist that belongs to a different project', function () {
        Sanctum::actingAs($this->owner);

        $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
        $otherSetlist = Setlist::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$otherSetlist->id}/sets",
            ['name' => 'Wrong Project Set']
        );

        $response->assertNotFound();
    });
});

describe('SetlistSong CRUD', function () {
    beforeEach(function () {
        $this->setlist = Setlist::factory()->create(['project_id' => $this->project->id]);
        $this->set = SetlistSet::factory()->create(['setlist_id' => $this->setlist->id]);

        $this->song = Song::factory()->create(['title' => 'Test Song']);
        $this->projectSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'song_id' => $this->song->id,
        ]);
    });

    it('adds a song to a set', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs",
            ['project_song_id' => $this->projectSong->id]
        );

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Song added to set')
            ->assertJsonPath('setlist_song.project_song_id', $this->projectSong->id)
            ->assertJsonPath('setlist_song.song.title', 'Test Song');

        $this->assertDatabaseHas('setlist_songs', [
            'set_id' => $this->set->id,
            'project_song_id' => $this->projectSong->id,
        ]);
    });

    it('adds a set note entry to a set', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs",
            ['notes' => 'Acoustic opening block.']
        );

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Song added to set')
            ->assertJsonPath('setlist_song.project_song_id', null)
            ->assertJsonPath('setlist_song.notes', 'Acoustic opening block.')
            ->assertJsonPath('setlist_song.song', null);

        $this->assertDatabaseHas('setlist_songs', [
            'set_id' => $this->set->id,
            'project_song_id' => null,
            'notes' => 'Acoustic opening block.',
        ]);
    });

    it('requires either a song id or note content when creating a set entry', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs",
            []
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['project_song_id']);
    });

    it('adds songs to a set in bulk', function () {
        Sanctum::actingAs($this->owner);

        $song2 = Song::factory()->create(['title' => 'Second Song']);
        $projectSong2 = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'song_id' => $song2->id,
        ]);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/bulk",
            ['project_song_ids' => [$this->projectSong->id, $projectSong2->id]]
        );

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Songs added to set')
            ->assertJsonCount(2, 'setlist_songs')
            ->assertJsonPath('setlist_songs.0.project_song_id', $this->projectSong->id)
            ->assertJsonPath('setlist_songs.1.project_song_id', $projectSong2->id);

        $this->assertDatabaseHas('setlist_songs', [
            'set_id' => $this->set->id,
            'project_song_id' => $this->projectSong->id,
            'order_index' => 0,
        ]);
        $this->assertDatabaseHas('setlist_songs', [
            'set_id' => $this->set->id,
            'project_song_id' => $projectSong2->id,
            'order_index' => 1,
        ]);
    });

    it('allows duplicate songs in bulk inserts', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/bulk",
            ['project_song_ids' => [$this->projectSong->id, $this->projectSong->id]]
        );

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Songs added to set')
            ->assertJsonCount(2, 'setlist_songs')
            ->assertJsonPath('setlist_songs.0.project_song_id', $this->projectSong->id)
            ->assertJsonPath('setlist_songs.1.project_song_id', $this->projectSong->id)
            ->assertJsonPath('setlist_songs.0.order_index', 0)
            ->assertJsonPath('setlist_songs.1.order_index', 1);

        expect(
            SetlistSong::query()
                ->where('set_id', $this->set->id)
                ->where('project_song_id', $this->projectSong->id)
                ->count()
        )->toBe(2);
    });

    it('removes a song from a set', function () {
        Sanctum::actingAs($this->owner);

        $setlistSong = SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => $this->projectSong->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/{$setlistSong->id}"
        );

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Song removed from set');

        $this->assertDatabaseMissing('setlist_songs', ['id' => $setlistSong->id]);
    });

    it('reorders songs in a set', function () {
        Sanctum::actingAs($this->owner);

        $song2 = Song::factory()->create();
        $projectSong2 = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'song_id' => $song2->id,
        ]);

        $setlistSong1 = SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => $this->projectSong->id,
            'order_index' => 0,
        ]);
        $setlistSong2 = SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => $projectSong2->id,
            'order_index' => 1,
        ]);

        // Reverse the order
        $response = $this->putJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/reorder",
            ['song_ids' => [$setlistSong2->id, $setlistSong1->id]]
        );

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Set entries reordered');

        $this->assertDatabaseHas('setlist_songs', [
            'id' => $setlistSong1->id,
            'order_index' => 1,
        ]);
        $this->assertDatabaseHas('setlist_songs', [
            'id' => $setlistSong2->id,
            'order_index' => 0,
        ]);
    });

    it('imports songs from pasted text', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/import-text",
            [
                'text' => "Wonderwall - Oasis\nFast Car - Tracy Chapman\nWonderwall - Oasis",
            ],
        );

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Songs imported from text.')
            ->assertJsonPath('meta.added_count', 3)
            ->assertJsonPath('meta.duplicate_count', 1)
            ->assertJsonPath('meta.duplicate_lines.0', 'Wonderwall - Oasis')
            ->assertJsonPath('setlist_songs.0.song.title', 'Wonderwall')
            ->assertJsonPath('setlist_songs.1.song.title', 'Fast Car')
            ->assertJsonPath('setlist_songs.2.song.title', 'Wonderwall');

        $this->assertDatabaseHas('songs', [
            'title' => 'Wonderwall',
            'artist' => 'Oasis',
        ]);
        $this->assertDatabaseHas('songs', [
            'title' => 'Fast Car',
            'artist' => 'Tracy Chapman',
        ]);
    });

    it('warns when pasted text adds a song that is already in the set', function () {
        Sanctum::actingAs($this->owner);

        $song = Song::factory()->create([
            'title' => 'Already Added Song',
            'artist' => 'Repeat Artist',
            'normalized_key' => Song::generateNormalizedKey(
                'Already Added Song',
                'Repeat Artist',
            ),
        ]);
        $projectSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'song_id' => $song->id,
        ]);

        SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => $projectSong->id,
        ]);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/import-text",
            ['text' => "{$song->title} - {$song->artist}"],
        );

        $response->assertStatus(201)
            ->assertJsonPath('meta.added_count', 1)
            ->assertJsonPath('meta.duplicate_count', 1)
            ->assertJsonPath('meta.duplicate_lines.0', "{$song->title} - {$song->artist}");

        expect(
            SetlistSong::query()
                ->where('set_id', $this->set->id)
                ->where('project_song_id', $projectSong->id)
                ->count()
        )->toBe(2);
    });

    it('updates notes for a setlist song', function () {
        Sanctum::actingAs($this->owner);

        $setlistSong = SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => $this->projectSong->id,
            'notes' => null,
        ]);

        $response = $this->putJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/{$setlistSong->id}",
            ['notes' => 'Kick off the chorus with extra dynamics.'],
        );

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Set song updated')
            ->assertJsonPath(
                'setlist_song.notes',
                'Kick off the chorus with extra dynamics.',
            );

        $this->assertDatabaseHas('setlist_songs', [
            'id' => $setlistSong->id,
            'notes' => 'Kick off the chorus with extra dynamics.',
        ]);
    });

    it('updates color for a setlist song', function () {
        Sanctum::actingAs($this->owner);

        $setlistSong = SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => $this->projectSong->id,
            'color_hex' => null,
        ]);

        $response = $this->putJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/{$setlistSong->id}",
            ['color_hex' => '#2563EB'],
        );

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Set song updated')
            ->assertJsonPath('setlist_song.color_hex', '#2563EB');

        $this->assertDatabaseHas('setlist_songs', [
            'id' => $setlistSong->id,
            'color_hex' => '#2563EB',
        ]);
    });

    it('updates color for a set note entry', function () {
        Sanctum::actingAs($this->owner);

        $setNote = SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => null,
            'notes' => 'Watch the transition lighting.',
            'color_hex' => null,
        ]);

        $response = $this->putJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/{$setNote->id}",
            ['color_hex' => '#F97316'],
        );

        $response->assertSuccessful()
            ->assertJsonPath('setlist_song.project_song_id', null)
            ->assertJsonPath('setlist_song.color_hex', '#F97316');

        $this->assertDatabaseHas('setlist_songs', [
            'id' => $setNote->id,
            'project_song_id' => null,
            'color_hex' => '#F97316',
        ]);
    });

    it('validates setlist song color format', function () {
        Sanctum::actingAs($this->owner);

        $setlistSong = SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => $this->projectSong->id,
        ]);

        $response = $this->putJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/{$setlistSong->id}",
            ['color_hex' => 'blue'],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['color_hex']);
    });

    it('validates project_song belongs to project', function () {
        Sanctum::actingAs($this->owner);

        $otherProject = Project::factory()->create();
        $otherProjectSong = ProjectSong::factory()->create([
            'project_id' => $otherProject->id,
        ]);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs",
            ['project_song_id' => $otherProjectSong->id]
        );

        $response->assertNotFound();
    });

    it('validates bulk song inserts belong to the project', function () {
        Sanctum::actingAs($this->owner);

        $otherProject = Project::factory()->create();
        $otherProjectSong = ProjectSong::factory()->create([
            'project_id' => $otherProject->id,
        ]);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/bulk",
            ['project_song_ids' => [$otherProjectSong->id]]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['project_song_ids.0']);
    });

    it('allows duplicate songs in same set', function () {
        Sanctum::actingAs($this->owner);

        SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => $this->projectSong->id,
        ]);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs",
            ['project_song_id' => $this->projectSong->id]
        );

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Song added to set')
            ->assertJsonPath('setlist_song.project_song_id', $this->projectSong->id);

        expect(
            SetlistSong::query()
                ->where('set_id', $this->set->id)
                ->where('project_song_id', $this->projectSong->id)
                ->count()
        )->toBe(2);
    });

    it('returns 404 for song in different set', function () {
        Sanctum::actingAs($this->owner);

        $otherSet = SetlistSet::factory()->create(['setlist_id' => $this->setlist->id]);
        $setlistSong = SetlistSong::factory()->create([
            'set_id' => $otherSet->id,
            'project_song_id' => $this->projectSong->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/{$setlistSong->id}"
        );

        $response->assertNotFound();
    });

    it('returns 404 when adding a song to a set for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs",
            ['project_song_id' => $this->projectSong->id]
        );

        $response->assertNotFound();
    });

    it('returns 404 when bulk adding songs for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/bulk",
            ['project_song_ids' => [$this->projectSong->id]]
        );

        $response->assertNotFound();
    });

    it('returns 404 when importing text for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/import-text",
            ['text' => 'Some Song - Some Artist']
        );

        $response->assertNotFound();
    });

    it('returns 404 when updating a setlist song for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $setlistSong = SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => $this->projectSong->id,
        ]);

        $response = $this->putJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/{$setlistSong->id}",
            ['notes' => 'Blocked update']
        );

        $response->assertNotFound();
    });

    it('returns 404 when deleting a setlist song for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $setlistSong = SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => $this->projectSong->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/{$setlistSong->id}"
        );

        $response->assertNotFound();
    });

    it('returns 404 when reordering songs for a project the user cannot access', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $setlistSong = SetlistSong::factory()->create([
            'set_id' => $this->set->id,
            'project_song_id' => $this->projectSong->id,
        ]);

        $response = $this->putJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/reorder",
            ['song_ids' => [$setlistSong->id]]
        );

        $response->assertNotFound();
    });

    it('returns 404 when adding a song to a set in a setlist from a different project', function () {
        Sanctum::actingAs($this->owner);

        $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
        $otherSetlist = Setlist::factory()->create(['project_id' => $otherProject->id]);
        $otherSet = SetlistSet::factory()->create(['setlist_id' => $otherSetlist->id]);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$otherSetlist->id}/sets/{$otherSet->id}/songs",
            ['project_song_id' => $this->projectSong->id]
        );

        $response->assertNotFound();
    });

    it('returns 404 when adding a song to a set that belongs to a different setlist', function () {
        Sanctum::actingAs($this->owner);

        $otherSetlist = Setlist::factory()->create(['project_id' => $this->project->id]);
        $otherSet = SetlistSet::factory()->create(['setlist_id' => $otherSetlist->id]);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$otherSet->id}/songs",
            ['project_song_id' => $this->projectSong->id]
        );

        $response->assertNotFound();
    });

    it('reports unresolved lines when create_missing_songs is false and song not found', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/import-text",
            [
                'text' => 'Nonexistent Song - Unknown Artist',
                'create_missing_songs' => false,
            ],
        );

        $response->assertStatus(201)
            ->assertJsonPath('meta.added_count', 0)
            ->assertJsonPath('meta.unresolved_count', 1)
            ->assertJsonPath('meta.unresolved_lines.0', 'Nonexistent Song - Unknown Artist');
    });

    it('treats song lines with only a title and no artist separator as unresolved', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$this->setlist->id}/sets/{$this->set->id}/songs/import-text",
            ['text' => 'JustATitle'],
        );

        $response->assertStatus(201)
            ->assertJsonPath('meta.added_count', 0)
            ->assertJsonPath('meta.unresolved_lines.0', 'JustATitle');
    });
});

describe('Cascade Deletes', function () {
    it('deletes sets when setlist is deleted', function () {
        Sanctum::actingAs($this->owner);

        $setlist = Setlist::factory()->create(['project_id' => $this->project->id]);
        $set = SetlistSet::factory()->create(['setlist_id' => $setlist->id]);

        $this->deleteJson("/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}");

        $this->assertDatabaseMissing('setlist_sets', ['id' => $set->id]);
    });

    it('deletes songs when set is deleted', function () {
        Sanctum::actingAs($this->owner);

        $setlist = Setlist::factory()->create(['project_id' => $this->project->id]);
        $set = SetlistSet::factory()->create(['setlist_id' => $setlist->id]);

        $projectSong = ProjectSong::factory()->create(['project_id' => $this->project->id]);
        $setlistSong = SetlistSong::factory()->create([
            'set_id' => $set->id,
            'project_song_id' => $projectSong->id,
        ]);

        $this->deleteJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}/sets/{$set->id}"
        );

        $this->assertDatabaseMissing('setlist_songs', ['id' => $setlistSong->id]);
    });
});
