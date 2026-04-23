<?php

declare(strict_types=1);

use App\Enums\PerformanceSessionMode;
use App\Enums\PerformanceSource;
use App\Models\PerformanceSession;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistSong;
use App\Models\Song;
use App\Models\SongPerformance;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);

    $this->setlist = Setlist::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'Performance Set',
    ]);
    $this->set = SetlistSet::factory()->create([
        'setlist_id' => $this->setlist->id,
        'order_index' => 0,
    ]);

    $songOne = Song::factory()->create([
        'title' => 'Opening Song',
        'artist' => 'Band A',
    ]);
    $songTwo = Song::factory()->create([
        'title' => 'Second Song',
        'artist' => 'Band B',
    ]);

    $this->projectSongOne = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $songOne->id,
    ]);
    $this->projectSongTwo = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $songTwo->id,
    ]);

    $this->setlistSongOne = SetlistSong::factory()->create([
        'set_id' => $this->set->id,
        'project_song_id' => $this->projectSongOne->id,
        'order_index' => 0,
    ]);
    $this->setlistSongTwo = SetlistSong::factory()->create([
        'set_id' => $this->set->id,
        'project_song_id' => $this->projectSongTwo->id,
        'order_index' => 1,
    ]);
});

it('returns 409 when starting a second active performance session in one project', function () {
    Sanctum::actingAs($this->owner);

    $first = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Smart->value,
            'seed' => 12345,
        ],
    );

    $first->assertStatus(201)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.mode', PerformanceSessionMode::Smart->value)
        ->assertJsonPath('data.items.0.status', 'pending');

    $second = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Manual->value,
        ],
    );

    $second->assertStatus(409)
        ->assertJsonPath(
            'message',
            'An active performance session already exists for this project.',
        );
});

it('assigns sequential performed order indexes as songs are completed', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Smart->value,
            'seed' => 42,
        ],
    )->assertStatus(201);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/complete",
        [
            'project_song_id' => $this->projectSongOne->id,
            'source' => PerformanceSource::Setlist->value,
            'setlist_song_id' => $this->setlistSongOne->id,
        ],
        ['Idempotency-Key' => 'complete-first'],
    )->assertOk();

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/complete",
        [
            'project_song_id' => $this->projectSongTwo->id,
            'source' => PerformanceSource::Setlist->value,
            'setlist_song_id' => $this->setlistSongTwo->id,
        ],
        ['Idempotency-Key' => 'complete-second'],
    )->assertOk();

    $this->assertDatabaseHas('performance_session_items', [
        'project_song_id' => $this->projectSongOne->id,
        'status' => 'performed',
        'performed_order_index' => 1,
    ]);
    $this->assertDatabaseHas('performance_session_items', [
        'project_song_id' => $this->projectSongTwo->id,
        'status' => 'performed',
        'performed_order_index' => 2,
    ]);

    $this->assertDatabaseHas('song_performances', [
        'project_song_id' => $this->projectSongOne->id,
        'performed_order_index' => 1,
    ]);
    $this->assertDatabaseHas('song_performances', [
        'project_song_id' => $this->projectSongTwo->id,
        'performed_order_index' => 2,
    ]);
});

it('tracks duplicate setlist song occurrences separately and does not double-log repeats', function () {
    Sanctum::actingAs($this->owner);

    $duplicateSetlistSong = SetlistSong::factory()->create([
        'set_id' => $this->set->id,
        'project_song_id' => $this->projectSongOne->id,
        'order_index' => 2,
    ]);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Manual->value,
        ],
    )->assertStatus(201);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/complete",
        [
            'project_song_id' => $this->projectSongOne->id,
            'source' => PerformanceSource::Setlist->value,
            'setlist_song_id' => $this->setlistSongOne->id,
        ],
        ['Idempotency-Key' => 'complete-duplicate-first'],
    )->assertOk();

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/complete",
        [
            'project_song_id' => $this->projectSongOne->id,
            'source' => PerformanceSource::Setlist->value,
            'setlist_song_id' => $duplicateSetlistSong->id,
        ],
        ['Idempotency-Key' => 'complete-duplicate-second'],
    )->assertOk();

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/complete",
        [
            'project_song_id' => $this->projectSongOne->id,
            'source' => PerformanceSource::Setlist->value,
            'setlist_song_id' => $duplicateSetlistSong->id,
        ],
        ['Idempotency-Key' => 'complete-duplicate-repeat'],
    )->assertOk();

    $this->assertDatabaseHas('performance_session_items', [
        'setlist_song_id' => $this->setlistSongOne->id,
        'status' => 'performed',
        'performed_order_index' => 1,
    ]);
    $this->assertDatabaseHas('performance_session_items', [
        'setlist_song_id' => $duplicateSetlistSong->id,
        'status' => 'performed',
        'performed_order_index' => 2,
    ]);

    expect(
        SongPerformance::query()
            ->where('project_song_id', $this->projectSongOne->id)
            ->where('performance_session_id', PerformanceSession::query()->value('id'))
            ->count()
    )->toBe(2);

    expect($this->projectSongOne->fresh()->performance_count)->toBe(2);
});

it('replays identical setlist create responses for duplicate idempotency keys', function () {
    Sanctum::actingAs($this->owner);

    $headers = ['Idempotency-Key' => 'setlist-create-duplicate-key'];

    $first = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists",
        ['name' => 'Idempotent Setlist'],
        $headers,
    );

    $second = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists",
        ['name' => 'Idempotent Setlist'],
        $headers,
    );

    $first->assertStatus(201)
        ->assertJsonPath('setlist.name', 'Idempotent Setlist');
    $second->assertStatus(201)
        ->assertHeader('X-Idempotent-Replay', '1')
        ->assertJsonPath('setlist.name', 'Idempotent Setlist');

    expect(
        Setlist::query()
            ->where('project_id', $this->project->id)
            ->where('name', 'Idempotent Setlist')
            ->count()
    )->toBe(1);
});

it('starts a manual performance session successfully', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Manual->value,
        ],
    );

    $response->assertStatus(201)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.mode', PerformanceSessionMode::Manual->value);
});

it('returns 404 when starting performance for project user cannot access', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Manual->value,
        ],
    );

    $response->assertNotFound();
});

it('stops an active performance session', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Manual->value,
        ],
    )->assertStatus(201);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/stop",
    );

    $response->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('returns null data when stopping with no active session', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/stop",
    );

    $response->assertOk()
        ->assertJsonPath('data', null);
});

it('returns 404 when stopping performance for inaccessible project', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/stop",
    );

    $response->assertNotFound();
});

it('returns current active performance session', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Smart->value,
            'seed' => 42,
        ],
    )->assertStatus(201);

    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current",
    );

    $response->assertOk()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.mode', PerformanceSessionMode::Smart->value);
});

it('returns null when no active performance session exists', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current",
    );

    $response->assertOk()
        ->assertJsonPath('data', null);
});

it('returns 404 when checking current session for inaccessible project', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current",
    );

    $response->assertNotFound();
});

it('returns 409 when completing a song with no active session', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/complete",
        [
            'project_song_id' => $this->projectSongOne->id,
            'source' => PerformanceSource::Repertoire->value,
        ],
        ['Idempotency-Key' => 'complete-no-session'],
    );

    $response->assertStatus(409)
        ->assertJsonPath('message', 'No active performance session found.');
});

it('returns 404 when completing with inaccessible project', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/complete",
        [
            'project_song_id' => $this->projectSongOne->id,
            'source' => PerformanceSource::Repertoire->value,
        ],
        ['Idempotency-Key' => 'complete-no-access'],
    );

    $response->assertNotFound();
});

it('returns 404 when completing with project song from different project', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Manual->value,
        ],
    )->assertStatus(201);

    $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
    $otherSong = Song::factory()->create();
    $otherProjectSong = ProjectSong::factory()->create([
        'project_id' => $otherProject->id,
        'song_id' => $otherSong->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/complete",
        [
            'project_song_id' => $otherProjectSong->id,
            'source' => PerformanceSource::Repertoire->value,
        ],
        ['Idempotency-Key' => 'complete-wrong-project'],
    );

    $response->assertNotFound();
});

it('completes a song from repertoire source without setlist_song_id', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Manual->value,
        ],
    )->assertStatus(201);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/complete",
        [
            'project_song_id' => $this->projectSongOne->id,
            'source' => PerformanceSource::Repertoire->value,
        ],
        ['Idempotency-Key' => 'complete-from-repertoire'],
    );

    $response->assertOk();
});

it('returns 409 when skipping with no active session', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/skip",
        [
            'project_song_id' => $this->projectSongOne->id,
        ],
    );

    $response->assertStatus(409)
        ->assertJsonPath('message', 'No active performance session found.');
});

it('returns 422 when skipping in manual mode', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Manual->value,
        ],
    )->assertStatus(201);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/skip",
        [
            'project_song_id' => $this->projectSongOne->id,
        ],
    );

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Skipping is only available in smart performance mode.');
});

it('returns 404 when skipping with inaccessible project', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/skip",
        [
            'project_song_id' => $this->projectSongOne->id,
        ],
    );

    $response->assertNotFound();
});

it('skips a song in smart mode', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Smart->value,
            'seed' => 42,
        ],
    )->assertStatus(201);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/skip",
        [
            'project_song_id' => $this->projectSongOne->id,
        ],
    );

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['skipped_item']]);
});

it('returns 404 when skipping project song from different project', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Smart->value,
            'seed' => 42,
        ],
    )->assertStatus(201);

    $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
    $otherSong = Song::factory()->create();
    $otherProjectSong = ProjectSong::factory()->create([
        'project_id' => $otherProject->id,
        'song_id' => $otherSong->id,
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/skip",
        [
            'project_song_id' => $otherProjectSong->id,
        ],
    );

    $response->assertNotFound();
});

it('returns 409 when requesting random with no active session', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/random",
    );

    $response->assertStatus(409)
        ->assertJsonPath('message', 'No active performance session found.');
});

it('returns 422 when requesting random in manual mode', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Manual->value,
        ],
    )->assertStatus(201);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/random",
    );

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Random selection is only available in smart performance mode.');
});

it('returns 404 when requesting random for inaccessible project', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/random",
    );

    $response->assertNotFound();
});

it('returns random recommendation in smart mode', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Smart->value,
            'seed' => 42,
        ],
    )->assertStatus(201);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/random",
    );

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

it('returns null when no pending songs remain for random', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/start",
        [
            'setlist_id' => $this->setlist->id,
            'mode' => PerformanceSessionMode::Smart->value,
            'seed' => 42,
        ],
    )->assertStatus(201);

    // Complete all songs
    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/complete",
        [
            'project_song_id' => $this->projectSongOne->id,
            'source' => PerformanceSource::Setlist->value,
            'setlist_song_id' => $this->setlistSongOne->id,
        ],
        ['Idempotency-Key' => 'complete-all-1'],
    )->assertOk();

    $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/complete",
        [
            'project_song_id' => $this->projectSongTwo->id,
            'source' => PerformanceSource::Setlist->value,
            'setlist_song_id' => $this->setlistSongTwo->id,
        ],
        ['Idempotency-Key' => 'complete-all-2'],
    )->assertOk();

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/performances/current/random",
    );

    $response->assertOk()
        ->assertJsonPath('data', null)
        ->assertJsonPath('meta.reason', 'No pending songs remain.');
});
