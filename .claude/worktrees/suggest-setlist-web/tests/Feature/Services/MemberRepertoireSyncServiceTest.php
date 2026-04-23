<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectSong;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistSong;
use App\Models\Song;
use App\Models\User;
use App\Services\MemberRepertoireSyncService;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    ProjectMember::factory()->create([
        'project_id' => $this->project->id,
        'user_id' => $this->member->id,
    ]);
    $this->service = app(MemberRepertoireSyncService::class);
});

describe('copyAllSongsToMember', function () {
    it('copies all owner songs to a member', function () {
        $song1 = Song::factory()->create();
        $song2 = Song::factory()->create();

        ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song1->id,
        ]);
        ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song2->id,
        ]);

        $copied = $this->service->copyAllSongsToMember($this->project, $this->member);

        expect($copied)->toBe(2);

        $memberSongs = ProjectSong::query()
            ->where('project_id', $this->project->id)
            ->where('user_id', $this->member->id)
            ->get();

        expect($memberSongs)->toHaveCount(2);
        expect($memberSongs->pluck('song_id')->sort()->values()->all())
            ->toBe([$song1->id, $song2->id]);

        // All member copies should have source_project_song_id set.
        $memberSongs->each(function (ProjectSong $ps) {
            expect($ps->source_project_song_id)->not->toBeNull();
            expect($ps->isOwnerCopy())->toBeFalse();
        });
    });

    it('is idempotent and skips existing copies', function () {
        $song = Song::factory()->create();
        ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song->id,
        ]);

        $copied1 = $this->service->copyAllSongsToMember($this->project, $this->member);
        $copied2 = $this->service->copyAllSongsToMember($this->project, $this->member);

        expect($copied1)->toBe(1);
        expect($copied2)->toBe(0);
    });
});

describe('copySongToMember', function () {
    it('copies song metadata to the member', function () {
        $song = Song::factory()->create();
        $ownerSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song->id,
            'notes' => 'Play softly',
        ]);

        $memberSong = $this->service->copySongToMember($ownerSong, $this->member);

        expect($memberSong->user_id)->toBe($this->member->id);
        expect($memberSong->song_id)->toBe($song->id);
        expect($memberSong->source_project_song_id)->toBe($ownerSong->id);
        expect($memberSong->notes)->toBe('Play softly');
    });
});

describe('copyAllSetlistsToMember', function () {
    it('copies active setlists with sets and songs', function () {
        $song = Song::factory()->create();
        $ownerSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song->id,
        ]);

        // Also create the member's copy of the song.
        $memberSong = $this->service->copySongToMember($ownerSong, $this->member);

        $setlist = Setlist::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'name' => 'Friday Set',
            'notes' => 'Owner notes',
        ]);
        $set = SetlistSet::factory()->create([
            'setlist_id' => $setlist->id,
            'name' => 'Set 1',
        ]);
        SetlistSong::factory()->create([
            'set_id' => $set->id,
            'project_song_id' => $ownerSong->id,
            'notes' => 'Owner song notes',
        ]);

        $copied = $this->service->copyAllSetlistsToMember($this->project, $this->member);

        expect($copied)->toBe(1);

        $memberSetlist = Setlist::query()
            ->where('project_id', $this->project->id)
            ->where('user_id', $this->member->id)
            ->first();

        expect($memberSetlist)->not->toBeNull();
        expect($memberSetlist->name)->toBe('Friday Set');
        expect($memberSetlist->notes)->toBeNull(); // Notes not copied.

        $memberSetlistSongs = $memberSetlist->sets->first()->songs;
        expect($memberSetlistSongs)->toHaveCount(1);
        expect($memberSetlistSongs->first()->project_song_id)->toBe($memberSong->id);
    });
});

describe('pullOwnerCopy', function () {
    it('creates an alternate version from the owner copy', function () {
        $song = Song::factory()->create();
        $ownerSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song->id,
            'notes' => 'Updated owner notes',
        ]);

        $memberSong = $this->service->copySongToMember($ownerSong, $this->member);

        // Owner updates their song.
        $ownerSong->update(['notes' => 'New owner notes']);

        $alternate = $this->service->pullOwnerCopy($memberSong);

        expect($alternate)->not->toBeNull();
        expect($alternate->user_id)->toBe($this->member->id);
        expect($alternate->notes)->toBe('New owner notes');
        expect($alternate->version_label)->toContain("Owner's Version");
        expect($alternate->isAlternateVersion())->toBeTrue();
    });

    it('returns null when no source exists', function () {
        $song = Song::factory()->create();
        $memberSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
            'song_id' => $song->id,
        ]);

        $result = $this->service->pullOwnerCopy($memberSong);

        expect($result)->toBeNull();
    });
});
