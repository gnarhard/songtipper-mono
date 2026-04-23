<?php

declare(strict_types=1);

use App\Mail\SetlistSuggestedMail;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistSong;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

function suggestSetlistProject(array $overrides = []): Project
{
    $owner = User::factory()->create();

    return Project::factory()->create(array_merge([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => true,
        'notify_on_request' => true,
        'min_suggested_setlist_songs' => 2,
        'max_suggested_setlist_songs' => 10,
    ], $overrides));
}

/**
 * @return list<ProjectSong>
 */
function createPublicSongs(Project $project, int $count = 5): array
{
    static $counter = 0;
    $songs = [];

    for ($i = 0; $i < $count; $i++) {
        $counter++;
        $song = Song::factory()->create([
            'title' => "Test Song {$counter}",
            'artist' => "Test Artist {$counter}",
        ]);

        $songs[] = $project->projectSongs()->create([
            'song_id' => $song->id,
            'is_public' => true,
        ]);
    }

    return $songs;
}

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('suggested_setlist:*');
});

// ── Page rendering ──────────────────────────────────────────────────────────

test('suggest setlist page renders and shows public songs', function () {
    $this->withoutVite();

    $project = suggestSetlistProject();
    $publicSong = Song::factory()->create(['title' => 'Visible Song']);
    $privateSong = Song::factory()->create(['title' => 'Hidden Song']);

    $project->projectSongs()->create(['song_id' => $publicSong->id, 'is_public' => true]);
    $project->projectSongs()->create(['song_id' => $privateSong->id, 'is_public' => false]);

    $this->get(route('project.suggest-setlist', ['projectSlug' => $project->slug]))
        ->assertSuccessful()
        ->assertSee('Visible Song')
        ->assertDontSee('Hidden Song');
});

test('suggest setlist page returns 404 for unknown slug', function () {
    $this->withoutVite();

    $this->get(route('project.suggest-setlist', ['projectSlug' => 'nonexistent-slug']))
        ->assertNotFound();
});

test('suggest setlist page respects public_repertoire_set_id override', function () {
    $this->withoutVite();

    $project = suggestSetlistProject();

    $setlist = Setlist::factory()->create(['project_id' => $project->id]);
    $set = SetlistSet::factory()->create(['setlist_id' => $setlist->id, 'order_index' => 0]);
    $project->forceFill(['public_repertoire_set_id' => $set->id])->save();

    $inSetSong = Song::factory()->create(['title' => 'In Set Song']);
    $outOfSetSong = Song::factory()->create(['title' => 'Out Of Set Song']);

    $inSetPs = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $inSetSong->id,
        'is_public' => true,
    ]);
    ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $outOfSetSong->id,
        'is_public' => true,
    ]);

    SetlistSong::factory()->create([
        'set_id' => $set->id,
        'project_song_id' => $inSetPs->id,
        'order_index' => 0,
    ]);

    Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
        ->assertSee('In Set Song')
        ->assertDontSee('Out Of Set Song');
});

// ── Valid submission ────────────────────────────────────────────────────────

test('valid submission queues email to project owner', function () {
    $project = suggestSetlistProject();
    $songs = createPublicSongs($project, 3);
    $songIds = array_map(fn ($ps) => $ps->id, $songs);

    $component = Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
        ->set('selectedProjectSongIds', $songIds)
        ->set('submitterName', 'Jane Doe')
        ->set('submitterEmail', 'jane@example.com')
        ->set('eventName', 'Friday Night')
        ->set('note', 'Play these please!');

    $this->travel(5)->seconds();

    $component->call('submit')
        ->assertSet('submitted', true)
        ->assertHasNoErrors();

    Mail::assertQueued(SetlistSuggestedMail::class, function (SetlistSuggestedMail $mail) use ($project) {
        return $mail->hasTo($project->owner->email)
            && $mail->submitterName === 'Jane Doe'
            && $mail->submitterEmail === 'jane@example.com'
            && $mail->eventName === 'Friday Night';
    });
});

// ── Count validation ────────────────────────────────────────────────────────

test('submission below min songs shows validation error', function () {
    $project = suggestSetlistProject(['min_suggested_setlist_songs' => 3]);
    $songs = createPublicSongs($project, 2);
    $songIds = array_map(fn ($ps) => $ps->id, $songs);

    $component = Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
        ->set('selectedProjectSongIds', $songIds)
        ->set('submitterName', 'Jane Doe')
        ->set('submitterEmail', 'jane@example.com');

    $this->travel(5)->seconds();

    $component->call('submit')
        ->assertHasErrors('selectedProjectSongIds');

    Mail::assertNothingQueued();
});

test('submission above max songs shows validation error', function () {
    $project = suggestSetlistProject(['max_suggested_setlist_songs' => 3]);
    $songs = createPublicSongs($project, 5);
    $songIds = array_map(fn ($ps) => $ps->id, $songs);

    $component = Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
        ->set('selectedProjectSongIds', $songIds)
        ->set('submitterName', 'Jane Doe')
        ->set('submitterEmail', 'jane@example.com');

    $this->travel(5)->seconds();

    $component->call('submit')
        ->assertHasErrors('selectedProjectSongIds');

    Mail::assertNothingQueued();
});

// ── Song ownership / visibility validation ──────────────────────────────────

test('cross-project song IDs are rejected', function () {
    $project = suggestSetlistProject(['min_suggested_setlist_songs' => 2]);
    $otherProject = suggestSetlistProject(['min_suggested_setlist_songs' => 2]);

    $ownSongs = createPublicSongs($project, 1);
    $otherSongs = createPublicSongs($otherProject, 2);

    $mixedIds = array_merge(
        array_map(fn ($ps) => $ps->id, $ownSongs),
        array_map(fn ($ps) => $ps->id, $otherSongs),
    );

    $component = Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
        ->set('selectedProjectSongIds', $mixedIds)
        ->set('submitterName', 'Jane Doe')
        ->set('submitterEmail', 'jane@example.com');

    $this->travel(5)->seconds();

    $component->call('submit')
        ->assertHasErrors('selectedProjectSongIds');

    Mail::assertNothingQueued();
});

test('non-public song IDs are rejected when no set override', function () {
    $project = suggestSetlistProject(['min_suggested_setlist_songs' => 2]);

    $publicSongs = createPublicSongs($project, 1);
    $privateSong = Song::factory()->create(['title' => 'Private']);
    $privatePs = $project->projectSongs()->create(['song_id' => $privateSong->id, 'is_public' => false]);

    $ids = [
        $publicSongs[0]->id,
        $privatePs->id,
    ];

    $component = Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
        ->set('selectedProjectSongIds', $ids)
        ->set('submitterName', 'Jane Doe')
        ->set('submitterEmail', 'jane@example.com');

    $this->travel(5)->seconds();

    $component->call('submit')
        ->assertHasErrors('selectedProjectSongIds');

    Mail::assertNothingQueued();
});

test('song IDs outside public_repertoire_set_id are rejected', function () {
    $project = suggestSetlistProject(['min_suggested_setlist_songs' => 2]);

    $setlist = Setlist::factory()->create(['project_id' => $project->id]);
    $set = SetlistSet::factory()->create(['setlist_id' => $setlist->id, 'order_index' => 0]);
    $project->forceFill(['public_repertoire_set_id' => $set->id])->save();

    $inSetSong = Song::factory()->create(['title' => 'In Set']);
    $outOfSetSong = Song::factory()->create(['title' => 'Out Of Set']);

    $inSetPs = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $inSetSong->id,
        'is_public' => true,
    ]);
    $outOfSetPs = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $outOfSetSong->id,
        'is_public' => true,
    ]);

    SetlistSong::factory()->create([
        'set_id' => $set->id,
        'project_song_id' => $inSetPs->id,
        'order_index' => 0,
    ]);

    $ids = [$inSetPs->id, $outOfSetPs->id];

    $component = Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
        ->set('selectedProjectSongIds', $ids)
        ->set('submitterName', 'Jane Doe')
        ->set('submitterEmail', 'jane@example.com');

    $this->travel(5)->seconds();

    $component->call('submit')
        ->assertHasErrors('selectedProjectSongIds');

    Mail::assertNothingQueued();
});

// ── Notify preference ───────────────────────────────────────────────────────

test('no email queued when notify_on_request is false', function () {
    $project = suggestSetlistProject(['notify_on_request' => false]);
    $songs = createPublicSongs($project, 3);
    $songIds = array_map(fn ($ps) => $ps->id, $songs);

    $component = Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
        ->set('selectedProjectSongIds', $songIds)
        ->set('submitterName', 'Jane Doe')
        ->set('submitterEmail', 'jane@example.com');

    $this->travel(5)->seconds();

    $component->call('submit')
        ->assertSet('submitted', true);

    Mail::assertNothingQueued();
});

// ── Anti-spam ───────────────────────────────────────────────────────────────

test('honeypot fill silently discards submission', function () {
    $project = suggestSetlistProject();
    $songs = createPublicSongs($project, 3);
    $songIds = array_map(fn ($ps) => $ps->id, $songs);

    $component = Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
        ->set('selectedProjectSongIds', $songIds)
        ->set('submitterName', 'Bot')
        ->set('submitterEmail', 'bot@spam.com')
        ->set('website', 'https://spam.example.com');

    $this->travel(5)->seconds();

    $component->call('submit')
        ->assertSet('submitted', true);

    Mail::assertNothingQueued();
});

test('submission within 3 seconds silently discards', function () {
    $project = suggestSetlistProject();
    $songs = createPublicSongs($project, 3);
    $songIds = array_map(fn ($ps) => $ps->id, $songs);

    Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
        ->set('selectedProjectSongIds', $songIds)
        ->set('submitterName', 'Quick User')
        ->set('submitterEmail', 'quick@example.com')
        ->call('submit')
        ->assertSet('submitted', true);

    Mail::assertNothingQueued();
});

// ── Rate limiting ───────────────────────────────────────────────────────────

test('4th submission from same IP is rate limited', function () {
    $project = suggestSetlistProject();

    for ($i = 0; $i < 3; $i++) {
        $songs = createPublicSongs($project, 3);
        $songIds = array_map(fn ($ps) => $ps->id, $songs);

        $component = Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
            ->set('selectedProjectSongIds', $songIds)
            ->set('submitterName', 'User')
            ->set('submitterEmail', 'user@example.com');

        $this->travel(5)->seconds();

        $component->call('submit')
            ->assertSet('submitted', true);
    }

    $songs = createPublicSongs($project, 3);
    $songIds = array_map(fn ($ps) => $ps->id, $songs);

    $component = Livewire::test('suggest-setlist-page', ['projectSlug' => $project->slug])
        ->set('selectedProjectSongIds', $songIds)
        ->set('submitterName', 'User')
        ->set('submitterEmail', 'user@example.com');

    $this->travel(5)->seconds();

    $component->call('submit')
        ->assertHasErrors('selectedProjectSongIds')
        ->assertSet('submitted', false);
});

// ── Mail content ────────────────────────────────────────────────────────────

test('email contains instrumental suffix for instrumental songs', function () {
    $project = suggestSetlistProject();

    $normalSong = Song::factory()->create(['title' => 'Normal Song', 'artist' => 'Normal Artist']);
    $instrumentalSong = Song::factory()->create(['title' => 'Piano Piece', 'artist' => 'Keys Player']);

    $normalPs = $project->projectSongs()->create(['song_id' => $normalSong->id, 'is_public' => true]);
    $instrumentalPs = $project->projectSongs()->create([
        'song_id' => $instrumentalSong->id,
        'is_public' => true,
        'instrumental' => true,
    ]);

    $mail = new SetlistSuggestedMail(
        project: $project,
        submitterName: 'Jane',
        submitterEmail: 'jane@example.com',
        eventName: null,
        note: null,
        projectSongIds: [$normalPs->id, $instrumentalPs->id],
    );

    $rendered = $mail->render();

    expect($rendered)->toContain('Normal Song')
        ->and($rendered)->toContain('Piano Piece (instrumental)')
        ->and($rendered)->not->toContain('Normal Song (instrumental)');
});

// ── UpdateProjectRequest tests ──────────────────────────────────────────────

test('PUT accepts and persists min and max suggested setlist songs', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->notAcceptingRequests()->create(['owner_user_id' => $owner->id]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/me/projects/{$project->id}", [
        'min_suggested_setlist_songs' => 3,
        'max_suggested_setlist_songs' => 15,
    ])->assertSuccessful();

    $project->refresh();
    expect($project->min_suggested_setlist_songs)->toBe(3)
        ->and($project->max_suggested_setlist_songs)->toBe(15);
});

test('PUT rejects min greater than max for suggested setlist songs', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->notAcceptingRequests()->create(['owner_user_id' => $owner->id]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/me/projects/{$project->id}", [
        'min_suggested_setlist_songs' => 20,
        'max_suggested_setlist_songs' => 5,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['min_suggested_setlist_songs']);
});

test('PUT rejects out-of-range suggested setlist song values', function (int $min, int $max) {
    $owner = User::factory()->create();
    $project = Project::factory()->notAcceptingRequests()->create(['owner_user_id' => $owner->id]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/me/projects/{$project->id}", [
        'min_suggested_setlist_songs' => $min,
        'max_suggested_setlist_songs' => $max,
    ])->assertUnprocessable();
})->with([
    'zero min' => [0, 10],
    'negative min' => [-1, 10],
    'over 100 max' => [1, 101],
]);
