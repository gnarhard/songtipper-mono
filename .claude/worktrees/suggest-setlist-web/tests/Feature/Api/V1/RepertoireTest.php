<?php

declare(strict_types=1);

use App\Enums\EnergyLevel;
use App\Enums\SongTheme;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Request as SongRequest;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistSong;
use App\Models\Song;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
        'slug' => 'test-project',
        'min_tip_cents' => 500,
    ]);
});

it('returns repertoire for a project', function () {
    $song = Song::factory()->create(['title' => 'Test Song', 'artist' => 'Test Artist']);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.song.title', 'Test Song')
        ->assertJsonPath('data.0.song.artist', 'Test Artist')
        ->assertJsonPath('meta.project.min_tip_cents', 500)
        ->assertJsonPath('meta.project.is_accepting_original_requests', true);
});

it('filters repertoire by search term', function () {
    $matchingSong = Song::factory()->create(['title' => 'Bohemian Rhapsody', 'artist' => 'Queen']);
    $nonMatchingSong = Song::factory()->create(['title' => 'Other Song', 'artist' => 'Other Artist']);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $matchingSong->id,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $nonMatchingSong->id,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?search=Bohemian');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.song.title', 'Bohemian Rhapsody');
});

it('filters repertoire by energy level', function () {
    $highEnergySong = Song::factory()->highEnergy()->create();
    $lowEnergySong = Song::factory()->lowEnergy()->create();

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $highEnergySong->id,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $lowEnergySong->id,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?energy_level=high');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.energy_level', EnergyLevel::High->value);
});

it('returns project-level energy and genre overrides when present', function () {
    $song = Song::factory()->create([
        'energy_level' => EnergyLevel::Low,
        'genre' => 'Jazz',
    ]);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'energy_level' => EnergyLevel::High,
        'genre' => 'Rock',
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.energy_level', EnergyLevel::High->value)
        ->assertJsonPath('data.0.genre', 'Rock');
});

it('returns instrumental songs in the public repertoire payload', function () {
    $song = Song::factory()->create([
        'title' => 'Instrumental Song',
        'artist' => 'House Band',
    ]);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'instrumental' => true,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.song.title', 'Instrumental Song')
        ->assertJsonPath('data.0.instrumental', true);
});

it('filters repertoire by overridden energy level', function () {
    $highSongOverriddenToLow = Song::factory()->highEnergy()->create();
    $lowSongOverriddenToHigh = Song::factory()->lowEnergy()->create();

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $highSongOverriddenToLow->id,
        'energy_level' => EnergyLevel::Low,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $lowSongOverriddenToHigh->id,
        'energy_level' => EnergyLevel::High,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?energy_level=high');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.song.id', $lowSongOverriddenToHigh->id)
        ->assertJsonPath('data.0.energy_level', EnergyLevel::High->value);
});

it('filters repertoire by era', function () {
    $song80s = Song::factory()->create(['era' => '80s']);
    $song90s = Song::factory()->create(['era' => '90s']);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song80s->id,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song90s->id,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?era=80s');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.era', '80s');
});

it('filters repertoire by genre', function () {
    $rockSong = Song::factory()->create(['genre' => 'Rock']);
    $jazzSong = Song::factory()->create(['genre' => 'Jazz']);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $rockSong->id,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $jazzSong->id,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?genre=Rock');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.genre', 'Rock');
});

it('filters repertoire by overridden genre', function () {
    $rockSongOverriddenToJazz = Song::factory()->create(['genre' => 'Rock']);
    $jazzSongOverriddenToRock = Song::factory()->create(['genre' => 'Jazz']);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $rockSongOverriddenToJazz->id,
        'genre' => 'Jazz',
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $jazzSongOverriddenToRock->id,
        'genre' => 'Rock',
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?genre=Rock');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.song.id', $jazzSongOverriddenToRock->id)
        ->assertJsonPath('data.0.genre', 'Rock');
});

it('filters repertoire by canonical theme values', function () {
    $loveSong = Song::factory()->create(['theme' => SongTheme::Love->value]);
    $partySong = Song::factory()->create(['theme' => SongTheme::Party->value]);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $loveSong->id,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $partySong->id,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?theme=love');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.theme', SongTheme::Love->value)
        ->assertJsonPath('data.0.song.id', $loveSong->id);
});

it('returns 422 when public repertoire theme filter is not canonical', function () {
    Song::factory()->create(['theme' => SongTheme::Party->value]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?theme=Party');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['theme']);
});

it('sorts repertoire by title', function () {
    $songA = Song::factory()->create(['title' => 'Alpha Song']);
    $songZ = Song::factory()->create(['title' => 'Zeta Song']);

    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $songZ->id,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $songA->id,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=title&direction=asc');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.song.title', 'Alpha Song')
        ->assertJsonPath('data.1.song.title', 'Zeta Song');
});

it('includes highest active tip for each song', function () {
    $song = Song::factory()->create();
    ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);

    SongRequest::factory()->active()->withTip(1000)->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);
    SongRequest::factory()->active()->withTip(2000)->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.highest_active_tip_cents', 2000);
});

it('returns 404 for non-existent project', function () {
    $response = $this->getJson('/api/v1/public/projects/non-existent/repertoire');

    $response->assertNotFound();
});

it('paginates repertoire results', function () {
    Song::factory()->count(30)->create()->each(function ($song) {
        ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'song_id' => $song->id,
        ]);
    });

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?per_page=10');

    $response->assertSuccessful()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 30);
});

it('caps per_page at 100', function () {
    Song::factory()->count(130)->create()->each(function ($song) {
        ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'song_id' => $song->id,
        ]);
    });

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?per_page=500');

    $response->assertSuccessful()
        ->assertJsonCount(100, 'data');
});

it('falls back to asc when sort direction is invalid', function () {
    $songA = Song::factory()->create(['title' => 'Alpha']);
    $songZ = Song::factory()->create(['title' => 'Zeta']);

    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $songA->id]);
    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $songZ->id]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=title&direction=INVALID');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.song.title', 'Alpha');
});

it('falls back to title when sort field is invalid', function () {
    $songA = Song::factory()->create(['title' => 'Alpha']);
    $songZ = Song::factory()->create(['title' => 'Zeta']);

    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $songA->id]);
    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $songZ->id]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=nonexistent&direction=asc');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.song.title', 'Alpha');
});

it('sorts repertoire by artist', function () {
    $songA = Song::factory()->create(['title' => 'Song One', 'artist' => 'Alpha Artist']);
    $songZ = Song::factory()->create(['title' => 'Song Two', 'artist' => 'Zeta Artist']);

    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $songZ->id]);
    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $songA->id]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=artist&direction=asc');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.song.artist', 'Alpha Artist');
});

it('sorts repertoire by genre with coalesce', function () {
    $songA = Song::factory()->create(['title' => 'Jazz Song', 'genre' => 'Jazz']);
    $songB = Song::factory()->create(['title' => 'Rock Song', 'genre' => 'Rock']);

    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $songB->id]);
    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $songA->id]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=genre&direction=asc');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.genre', 'Jazz');
});

it('sorts repertoire by theme with coalesce', function () {
    $loveSong = Song::factory()->create(['title' => 'Love Song', 'theme' => SongTheme::Love->value]);
    $partySong = Song::factory()->create(['title' => 'Party Song', 'theme' => SongTheme::Party->value]);

    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $loveSong->id]);
    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $partySong->id]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=theme&direction=asc');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('sorts repertoire by highest_active_tip_cents', function () {
    $lowTipSong = Song::factory()->create(['title' => 'Low Tip']);
    $highTipSong = Song::factory()->create(['title' => 'High Tip']);

    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $lowTipSong->id]);
    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $highTipSong->id]);

    SongRequest::factory()->active()->withTip(500)->create([
        'project_id' => $this->project->id,
        'song_id' => $lowTipSong->id,
    ]);
    SongRequest::factory()->active()->withTip(2000)->create([
        'project_id' => $this->project->id,
        'song_id' => $highTipSong->id,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=highest_active_tip_cents&direction=desc');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.song.title', 'High Tip');
});

it('sorts repertoire by era', function () {
    $song80s = Song::factory()->create(['title' => '80s Hit', 'era' => '80s']);
    $song90s = Song::factory()->create(['title' => '90s Hit', 'era' => '90s']);

    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $song90s->id]);
    ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $song80s->id]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=era&direction=asc');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.era', '80s');
});

it('sorts repertoire by era chronologically across 2-digit and 4-digit decades', function () {
    // '80s' (two-digit, means 1980s) must sort BEFORE '2020s' (four-digit),
    // even though alphabetically '2' < '8'.
    $song70s = Song::factory()->create(['title' => 'Hotel California', 'era' => '1970s']);
    $song80s = Song::factory()->create(['title' => 'Africa', 'era' => '80s']);
    $song90s = Song::factory()->create(['title' => 'Wonderwall', 'era' => '90s']);
    $song2020s = Song::factory()->create(['title' => 'Flowers', 'era' => '2020s']);

    foreach ([$song70s, $song80s, $song90s, $song2020s] as $song) {
        ProjectSong::factory()->create(['project_id' => $this->project->id, 'song_id' => $song->id]);
    }

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=era&direction=asc');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.song.title', 'Hotel California')
        ->assertJsonPath('data.1.song.title', 'Africa')
        ->assertJsonPath('data.2.song.title', 'Wonderwall')
        ->assertJsonPath('data.3.song.title', 'Flowers');

    $descending = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=era&direction=desc');

    $descending->assertSuccessful()
        ->assertJsonPath('data.0.song.title', 'Flowers')
        ->assertJsonPath('data.1.song.title', 'Wonderwall')
        ->assertJsonPath('data.2.song.title', 'Africa')
        ->assertJsonPath('data.3.song.title', 'Hotel California');
});

/**
 * Helpers for the public_repertoire_set_id branch, which applies DISTINCT to the
 * query. Sorting by genre / era / theme / highest_active_tip_cents used to produce
 * "ORDER BY not in SELECT list; incompatible with DISTINCT" errors on MySQL.
 */
function bindPublicRepertoireSet(Project $project): SetlistSet
{
    $setlist = Setlist::factory()->create(['project_id' => $project->id]);
    $set = SetlistSet::factory()->create(['setlist_id' => $setlist->id, 'order_index' => 0]);
    $project->forceFill(['public_repertoire_set_id' => $set->id])->save();

    return $set;
}

function addProjectSongToSet(Project $project, SetlistSet $set, Song $song, int $order): ProjectSong
{
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
    ]);

    SetlistSong::factory()->create([
        'set_id' => $set->id,
        'project_song_id' => $projectSong->id,
        'order_index' => $order,
    ]);

    return $projectSong;
}

it('sorts repertoire by genre when backed by a public repertoire set', function () {
    $set = bindPublicRepertoireSet($this->project);

    $jazzSong = Song::factory()->create(['title' => 'Take Five', 'genre' => 'Jazz']);
    $rockSong = Song::factory()->create(['title' => 'Thunderstruck', 'genre' => 'Rock']);

    addProjectSongToSet($this->project, $set, $rockSong, 0);
    addProjectSongToSet($this->project, $set, $jazzSong, 1);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=genre&direction=asc');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.genre', 'Jazz')
        ->assertJsonPath('data.1.genre', 'Rock');
});

it('sorts repertoire by era when backed by a public repertoire set', function () {
    $set = bindPublicRepertoireSet($this->project);

    $song70s = Song::factory()->create(['title' => 'Hotel California', 'era' => '70s']);
    $song90s = Song::factory()->create(['title' => 'Wonderwall', 'era' => '90s']);

    addProjectSongToSet($this->project, $set, $song90s, 0);
    addProjectSongToSet($this->project, $set, $song70s, 1);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=era&direction=asc');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.era', '70s');
});

it('sorts repertoire by theme when backed by a public repertoire set', function () {
    $set = bindPublicRepertoireSet($this->project);

    $loveSong = Song::factory()->create(['title' => 'Love Song', 'theme' => SongTheme::Love->value]);
    $partySong = Song::factory()->create(['title' => 'Party Song', 'theme' => SongTheme::Party->value]);

    addProjectSongToSet($this->project, $set, $loveSong, 0);
    addProjectSongToSet($this->project, $set, $partySong, 1);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=theme&direction=asc');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('sorts repertoire by highest_active_tip_cents when backed by a public repertoire set', function () {
    $set = bindPublicRepertoireSet($this->project);

    $lowTipSong = Song::factory()->create(['title' => 'Low Tip']);
    $highTipSong = Song::factory()->create(['title' => 'High Tip']);

    addProjectSongToSet($this->project, $set, $lowTipSong, 0);
    addProjectSongToSet($this->project, $set, $highTipSong, 1);

    SongRequest::factory()->active()->withTip(500)->create([
        'project_id' => $this->project->id,
        'song_id' => $lowTipSong->id,
    ]);
    SongRequest::factory()->active()->withTip(2000)->create([
        'project_id' => $this->project->id,
        'song_id' => $highTipSong->id,
    ]);

    $response = $this->getJson('/api/v1/public/projects/test-project/repertoire?sort=highest_active_tip_cents&direction=desc');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.song.title', 'High Tip');
});
