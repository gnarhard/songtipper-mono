<?php

declare(strict_types=1);

use App\Enums\EnergyLevel;
use App\Enums\SongTheme;
use App\Models\Chart;
use App\Models\ChartRender;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Request as SongRequest;
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
    $this->song = Song::factory()->create();
    $this->projectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
        'performance_count' => 0,
        'last_performed_at' => null,
    ]);
});

describe('Update needs_improvement flag', function () {
    it('allows updating needs_improvement to true', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}", [
            'needs_improvement' => true,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project_song.needs_improvement', true);

        $this->assertDatabaseHas('project_songs', [
            'id' => $this->projectSong->id,
            'needs_improvement' => true,
        ]);
    });

    it('allows updating needs_improvement to false', function () {
        $this->projectSong->update(['needs_improvement' => true]);
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}", [
            'needs_improvement' => false,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project_song.needs_improvement', false);

        $this->assertDatabaseHas('project_songs', [
            'id' => $this->projectSong->id,
            'needs_improvement' => false,
        ]);
    });
});

describe('Instrumental flag', function () {
    it('stores the instrumental flag when creating a repertoire song', function () {
        $newSong = Song::factory()->create();
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
            'song_id' => $newSong->id,
            'instrumental' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('project_song.instrumental', true);

        $projectSongId = $response->json('project_song.id');
        expect($projectSongId)->toBeInt();

        $this->assertDatabaseHas('project_songs', [
            'id' => $projectSongId,
            'instrumental' => true,
        ]);
    });

    it('allows updating the instrumental flag', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}", [
            'instrumental' => true,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project_song.instrumental', true);

        $this->assertDatabaseHas('project_songs', [
            'id' => $this->projectSong->id,
            'instrumental' => true,
        ]);
    });
});

describe('Update energy and genre overrides', function () {
    it('stores energy and genre on project_songs without changing global song metadata', function () {
        $this->song->update([
            'energy_level' => EnergyLevel::Low,
            'genre' => 'Jazz',
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}", [
            'energy_level' => 'high',
            'genre' => 'Rock',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project_song.energy_level', 'high')
            ->assertJsonPath('project_song.genre', 'Rock');

        $this->assertDatabaseHas('project_songs', [
            'id' => $this->projectSong->id,
            'energy_level' => 'high',
            'genre' => 'Rock',
        ]);

        $this->assertDatabaseHas('songs', [
            'id' => $this->song->id,
            'energy_level' => 'low',
            'genre' => 'Jazz',
        ]);
    });

    it('falls back to song metadata when energy and genre overrides are cleared', function () {
        $this->song->update([
            'energy_level' => EnergyLevel::Medium,
            'genre' => 'Pop',
        ]);
        $this->projectSong->update([
            'energy_level' => EnergyLevel::High,
            'genre' => 'Rock',
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}", [
            'energy_level' => null,
            'genre' => null,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project_song.energy_level', 'medium')
            ->assertJsonPath('project_song.genre', 'Pop');

        $this->assertDatabaseHas('project_songs', [
            'id' => $this->projectSong->id,
            'energy_level' => null,
            'genre' => null,
        ]);
    });
});

describe('Create repertoire songs with energy and genre', function () {
    it('stores overrides for existing songs when energy and genre are provided', function () {
        $existingSong = Song::factory()->create([
            'energy_level' => EnergyLevel::Low,
            'genre' => 'Jazz',
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
            'song_id' => $existingSong->id,
            'energy_level' => 'high',
            'genre' => 'Rock',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('project_song.energy_level', 'high')
            ->assertJsonPath('project_song.genre', 'Rock');

        $projectSongId = $response->json('project_song.id');
        expect($projectSongId)->toBeInt();

        $this->assertDatabaseHas('project_songs', [
            'id' => $projectSongId,
            'project_id' => $this->project->id,
            'song_id' => $existingSong->id,
            'energy_level' => 'high',
            'genre' => 'Rock',
        ]);

        $this->assertDatabaseHas('songs', [
            'id' => $existingSong->id,
            'energy_level' => 'low',
            'genre' => 'Jazz',
        ]);
    });

    it('stores energy and genre on new songs and inherits them by default', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
            'title' => 'Inheritance Song',
            'artist' => 'Inheritance Artist',
            'energy_level' => 'medium',
            'genre' => 'Pop',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('project_song.energy_level', 'medium')
            ->assertJsonPath('project_song.genre', 'Pop');

        $projectSongId = $response->json('project_song.id');
        $songId = $response->json('project_song.song.id');

        expect($projectSongId)->toBeInt();
        expect($songId)->toBeInt();

        $this->assertDatabaseHas('songs', [
            'id' => $songId,
            'title' => 'Inheritance Song',
            'artist' => 'Inheritance Artist',
            'energy_level' => 'medium',
            'genre' => 'Pop',
        ]);

        $this->assertDatabaseHas('project_songs', [
            'id' => $projectSongId,
            'song_id' => $songId,
            'energy_level' => null,
            'genre' => null,
        ]);
    });
});

describe('Era normalization on repertoire writes', function () {
    it('stores 1900s era values in short decade format when creating songs', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
            'title' => 'Era Create Song',
            'artist' => 'Era Artist',
            'era' => '1990s',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('project_song.era', '90s');

        $songId = $response->json('project_song.song.id');
        expect($songId)->toBeInt();

        $this->assertDatabaseHas('songs', [
            'id' => $songId,
            'era' => '90s',
        ]);
    });

    it('stores 1900s era values in short decade format when updating songs', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}", [
            'era' => '1980s',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project_song.era', '80s');

        $this->assertDatabaseHas('songs', [
            'id' => $this->song->id,
            'era' => '80s',
        ]);
    });
});

describe('Update capo metadata', function () {
    it('allows updating capo to a valid value', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}", [
            'capo' => 3,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project_song.capo', 3);

        $this->assertDatabaseHas('project_songs', [
            'id' => $this->projectSong->id,
            'capo' => 3,
        ]);
    });

    it('rejects capo values outside allowed range', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}", [
            'capo' => 15,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['capo']);
    });
});

describe('Project-song notes', function () {
    it('stores notes when creating a repertoire song', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
            'title' => 'Note Create Song',
            'artist' => 'Note Artist',
            'notes' => 'Play this one after the banter break.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('project_song.notes', 'Play this one after the banter break.');

        $projectSongId = $response->json('project_song.id');
        expect($projectSongId)->toBeInt();

        $this->assertDatabaseHas('project_songs', [
            'id' => $projectSongId,
            'notes' => 'Play this one after the banter break.',
        ]);
    });

    it('allows updating and clearing project-song notes', function () {
        Sanctum::actingAs($this->owner);

        $updatedResponse = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}", [
            'notes' => 'Watch the downbeat before verse two.',
        ]);

        $updatedResponse->assertSuccessful()
            ->assertJsonPath('project_song.notes', 'Watch the downbeat before verse two.');

        $this->assertDatabaseHas('project_songs', [
            'id' => $this->projectSong->id,
            'notes' => 'Watch the downbeat before verse two.',
        ]);

        $clearedResponse = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}", [
            'notes' => null,
        ]);

        $clearedResponse->assertSuccessful()
            ->assertJsonPath('project_song.notes', null);

        $this->assertDatabaseHas('project_songs', [
            'id' => $this->projectSong->id,
            'notes' => null,
        ]);
    });
});

describe('Performance tracking fields in repertoire', function () {
    it('includes performance tracking fields in repertoire response', function () {
        $this->projectSong->update(['instrumental' => true]);

        SongRequest::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'song_id' => $this->song->id,
            'tip_amount_cents' => 500,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/repertoire");

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'instrumental',
                        'needs_improvement',
                        'performance_count',
                        'request_count',
                        'total_tip_amount_cents',
                        'last_performed_at',
                    ],
                ],
            ])
            ->assertJsonPath('data.0.instrumental', true)
            ->assertJsonPath('data.0.needs_improvement', false)
            ->assertJsonPath('data.0.performance_count', 0)
            ->assertJsonPath('data.0.request_count', 2)
            ->assertJsonPath('data.0.total_tip_amount_cents', 1000)
            ->assertJsonPath('data.0.last_performed_at', null);
    });
});

describe('Log performance from repertoire', function () {
    it('logs performance from repertoire source', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'repertoire',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Performance logged successfully.')
            ->assertJsonPath('project_song.performance_count', 1)
            ->assertJsonPath('performance.source', 'repertoire')
            ->assertJsonStructure([
                'performance' => ['id', 'performed_at', 'source'],
            ]);

        $this->assertDatabaseHas('song_performances', [
            'project_id' => $this->project->id,
            'project_song_id' => $this->projectSong->id,
            'performer_user_id' => $this->owner->id,
            'source' => 'repertoire',
        ]);

        $this->assertDatabaseHas('project_songs', [
            'id' => $this->projectSong->id,
            'performance_count' => 1,
        ]);

        expect($this->projectSong->fresh()->last_performed_at)->not->toBeNull();
    });

    it('logs performance with custom performed_at time', function () {
        Sanctum::actingAs($this->owner);

        $performedAt = now()->subHours(2);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'repertoire',
            'performed_at' => $performedAt->toIso8601String(),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('song_performances', [
            'project_song_id' => $this->projectSong->id,
            'source' => 'repertoire',
        ]);
    });

    it('increments performance_count correctly', function () {
        $this->projectSong->update(['performance_count' => 5]);
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'repertoire',
        ]);

        $this->assertDatabaseHas('project_songs', [
            'id' => $this->projectSong->id,
            'performance_count' => 6,
        ]);
    });

    it('updates last_performed_at to most recent performance', function () {
        $oldPerformance = now()->subDays(5);
        $this->projectSong->update(['last_performed_at' => $oldPerformance]);

        Sanctum::actingAs($this->owner);

        $newPerformance = now();

        $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'repertoire',
            'performed_at' => $newPerformance->toIso8601String(),
        ]);

        $fresh = $this->projectSong->fresh();
        expect($fresh->last_performed_at->diffInSeconds($newPerformance, false))
            ->toBeLessThan(2);
    });

    it('does not update last_performed_at with older performance', function () {
        $recentPerformance = now();
        $this->projectSong->update(['last_performed_at' => $recentPerformance]);

        Sanctum::actingAs($this->owner);

        $olderPerformance = now()->subDays(3);

        $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'repertoire',
            'performed_at' => $olderPerformance->toIso8601String(),
        ]);

        $fresh = $this->projectSong->fresh();
        expect($fresh->last_performed_at->toDateTimeString())
            ->toBe($recentPerformance->toDateTimeString());
    });
});

describe('Log performance from setlist', function () {
    it('logs performance from setlist source with setlist references', function () {
        $setlist = Setlist::factory()->create([
            'project_id' => $this->project->id,
        ]);
        $set = SetlistSet::factory()->create([
            'setlist_id' => $setlist->id,
        ]);
        $setlistSong = SetlistSong::factory()->create([
            'set_id' => $set->id,
            'project_song_id' => $this->projectSong->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'setlist',
            'setlist_id' => $setlist->id,
            'set_id' => $set->id,
            'setlist_song_id' => $setlistSong->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('performance.source', 'setlist')
            ->assertJsonPath('performance.setlist_id', $setlist->id)
            ->assertJsonPath('performance.set_id', $set->id)
            ->assertJsonPath('performance.setlist_song_id', $setlistSong->id);

        $this->assertDatabaseHas('song_performances', [
            'project_song_id' => $this->projectSong->id,
            'source' => 'setlist',
            'setlist_id' => $setlist->id,
            'set_id' => $set->id,
            'setlist_song_id' => $setlistSong->id,
        ]);
    });
});

describe('Deleting repertoire songs', function () {
    it('cascades deletion to matching charts and chart renders', function () {
        $matchingChart = Chart::factory()->create([
            'owner_user_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'song_id' => $this->song->id,
        ]);

        $matchingLightRender = ChartRender::factory()->create([
            'chart_id' => $matchingChart->id,
            'page_number' => 1,
        ]);
        $matchingDarkRender = ChartRender::factory()->dark()->forPage(2)->create([
            'chart_id' => $matchingChart->id,
        ]);

        $otherSong = Song::factory()->create();
        ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'song_id' => $otherSong->id,
        ]);
        $sameProjectOtherSongChart = Chart::factory()->create([
            'owner_user_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'song_id' => $otherSong->id,
        ]);

        $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
        ProjectSong::factory()->create([
            'project_id' => $otherProject->id,
            'song_id' => $this->song->id,
        ]);
        $otherProjectSameSongChart = Chart::factory()->create([
            'owner_user_id' => $this->owner->id,
            'project_id' => $otherProject->id,
            'song_id' => $this->song->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}");

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Song removed from repertoire.');

        $this->assertDatabaseMissing('project_songs', [
            'id' => $this->projectSong->id,
        ]);
        $this->assertDatabaseMissing('charts', [
            'id' => $matchingChart->id,
        ]);
        $this->assertDatabaseMissing('chart_renders', [
            'id' => $matchingLightRender->id,
        ]);
        $this->assertDatabaseMissing('chart_renders', [
            'id' => $matchingDarkRender->id,
        ]);

        $this->assertDatabaseHas('charts', [
            'id' => $sameProjectOtherSongChart->id,
        ]);
        $this->assertDatabaseHas('charts', [
            'id' => $otherProjectSameSongChart->id,
        ]);
    });
});

describe('Repertoire index theme filter', function () {
    it('filters private repertoire by theme', function () {
        $loveSong = Song::factory()->create(['title' => 'Love Hit', 'theme' => SongTheme::Love->value]);
        $partySong = Song::factory()->create(['title' => 'Party Hit', 'theme' => SongTheme::Party->value]);

        ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $loveSong->id]);
        ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $partySong->id]);

        Sanctum::actingAs($this->owner);

        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/repertoire?theme=love");

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.song.title', 'Love Hit');
    });

    it('returns 404 when listing repertoire for inaccessible project', function () {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['owner_user_id' => $otherUser->id]);

        Sanctum::actingAs($this->owner);

        $response = $this->getJson("/api/v1/me/projects/{$otherProject->id}/repertoire");

        $response->assertNotFound();
    });
});

describe('Repertoire store edge cases', function () {
    it('returns 404 when storing song for inaccessible project', function () {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['owner_user_id' => $otherUser->id]);

        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$otherProject->id}/repertoire", [
            'title' => 'Blocked Song',
            'artist' => 'Blocked Artist',
        ]);

        $response->assertNotFound();
    });

    it('returns 409 when song is already in repertoire', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
            'song_id' => $this->song->id,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'This song is already in the repertoire.');
    });

    it('creates a mashup song with a fresh Song record', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
            'title' => 'Mashup Song',
            'artist' => 'DJ Mashup',
            'mashup' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('project_song.mashup', true);
    });

    it('updates mashup flag on existing project song', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}", [
            'mashup' => true,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project_song.mashup', true);
    });
});

describe('Repertoire update authorization', function () {
    it('returns 404 when updating song for inaccessible project', function () {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['owner_user_id' => $otherUser->id]);
        $otherSong = Song::factory()->create();
        $otherProjectSong = ProjectSong::factory()->create([
            'project_id' => $otherProject->id,
            'song_id' => $otherSong->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$otherProject->id}/repertoire/{$otherProjectSong->id}", [
            'notes' => 'test',
        ]);

        $response->assertNotFound();
    });

    it('returns 403 when updating song belonging to different project', function () {
        $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
        $otherSong = Song::factory()->create();
        $otherProjectSong = ProjectSong::factory()->create([
            'project_id' => $otherProject->id,
            'song_id' => $otherSong->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$otherProjectSong->id}", [
            'notes' => 'test',
        ]);

        $response->assertForbidden();
    });
});

describe('Repertoire delete authorization', function () {
    it('returns 404 when deleting song for inaccessible project', function () {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['owner_user_id' => $otherUser->id]);
        $otherSong = Song::factory()->create();
        $otherProjectSong = ProjectSong::factory()->create([
            'project_id' => $otherProject->id,
            'song_id' => $otherSong->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->deleteJson("/api/v1/me/projects/{$otherProject->id}/repertoire/{$otherProjectSong->id}");

        $response->assertNotFound();
    });

    it('returns 403 when deleting song belonging to different project', function () {
        $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
        $otherSong = Song::factory()->create();
        $otherProjectSong = ProjectSong::factory()->create([
            'project_id' => $otherProject->id,
            'song_id' => $otherSong->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$otherProjectSong->id}");

        $response->assertForbidden();
    });
});

describe('Validation and authorization', function () {
    it('rejects invalid source', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'invalid_source',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('source');
    });

    it('requires source field', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('source');
    });

    it('rejects unauthorized user', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'repertoire',
        ]);

        $response->assertNotFound();
    });

    it('rejects unauthenticated request', function () {
        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'repertoire',
        ]);

        $response->assertUnauthorized();
    });

    it('rejects projectSong from different project', function () {
        $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
        $otherProjectSong = ProjectSong::factory()->create([
            'project_id' => $otherProject->id,
            'song_id' => $this->song->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$otherProjectSong->id}/performances", [
            'source' => 'repertoire',
        ]);

        $response->assertForbidden();
    });

    it('rejects setlist metadata for repertoire source', function () {
        $setlist = Setlist::factory()->create([
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'repertoire',
            'setlist_id' => $setlist->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('setlist_id');
    });

    it('rejects setlist references from another project', function () {
        $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
        $otherSetlist = Setlist::factory()->create([
            'project_id' => $otherProject->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'setlist',
            'setlist_id' => $otherSetlist->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('setlist_id');
    });

    it('rejects setlist_song_id that belongs to a different repertoire song', function () {
        $setlist = Setlist::factory()->create([
            'project_id' => $this->project->id,
        ]);
        $set = SetlistSet::factory()->create([
            'setlist_id' => $setlist->id,
        ]);

        $otherProjectSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
        ]);
        $setlistSong = SetlistSong::factory()->create([
            'set_id' => $set->id,
            'project_song_id' => $otherProjectSong->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/{$this->projectSong->id}/performances", [
            'source' => 'setlist',
            'setlist_id' => $setlist->id,
            'set_id' => $set->id,
            'setlist_song_id' => $setlistSong->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('setlist_song_id');
    });
});
