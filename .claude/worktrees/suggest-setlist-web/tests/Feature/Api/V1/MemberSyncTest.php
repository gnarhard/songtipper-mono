<?php

declare(strict_types=1);

use App\Enums\ChartTheme;
use App\Jobs\CopySetlistToMember;
use App\Jobs\FanOutSongToMembers;
use App\Jobs\SyncRepertoireToMember;
use App\Models\Chart;
use App\Models\ChartAnnotationVersion;
use App\Models\ChartRender;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectSong;
use App\Models\Setlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
});

describe('Member Join Sync', function () {
    it('dispatches sync job when a new member is added', function () {
        Queue::fake();
        Sanctum::actingAs($this->owner);

        $member = User::factory()->create();

        $this->postJson("/api/v1/me/projects/{$this->project->id}/members", [
            'email' => $member->email,
        ])->assertStatus(201);

        Queue::assertPushed(SyncRepertoireToMember::class, function ($job) use ($member) {
            return $job->projectId === $this->project->id
                && $job->memberId === $member->id;
        });
    });
});

describe('Owner Song Fan-Out', function () {
    it('dispatches fan-out job when owner adds a song', function () {
        Queue::fake();
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
            'title' => 'Wonderwall',
            'artist' => 'Oasis',
        ])->assertStatus(201);

        Queue::assertPushed(FanOutSongToMembers::class);
    });

    it('does not dispatch fan-out when a member adds a song', function () {
        Queue::fake();

        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
        ]);

        Sanctum::actingAs($member);

        $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire", [
            'title' => 'Creep',
            'artist' => 'Radiohead',
        ])->assertStatus(201);

        Queue::assertNotPushed(FanOutSongToMembers::class);
    });
});

describe('Repertoire User Scoping', function () {
    it('only shows the authenticated user songs', function () {
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
        ]);

        $song = Song::factory()->create();

        // Owner has 1 song.
        ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song->id,
            'notes' => 'Owner notes',
        ]);

        // Member has same song but different notes.
        ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
            'song_id' => $song->id,
            'notes' => 'Member notes',
        ]);

        // Owner sees their version.
        Sanctum::actingAs($this->owner);
        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/repertoire");
        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.notes', 'Owner notes');

        // Member sees their version.
        Sanctum::actingAs($member);
        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/repertoire");
        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.notes', 'Member notes');
    });
});

describe('Setlist User Scoping', function () {
    it('only shows the authenticated user setlists', function () {
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
        ]);

        Setlist::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'name' => 'Owner Setlist',
        ]);

        Setlist::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
            'name' => 'Member Setlist',
        ]);

        Sanctum::actingAs($this->owner);
        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/setlists");
        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Owner Setlist');

        Sanctum::actingAs($member);
        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/setlists");
        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Member Setlist');
    });
});

describe('Pull Owner Copy', function () {
    it('creates an alternate from the owner version', function () {
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
        ]);

        $song = Song::factory()->create();

        $ownerSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song->id,
            'notes' => 'Latest owner notes',
        ]);

        $memberSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
            'song_id' => $song->id,
            'source_project_song_id' => $ownerSong->id,
            'notes' => 'Old member notes',
        ]);

        Sanctum::actingAs($member);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/repertoire/{$memberSong->id}/pull-owner-copy"
        );

        $response->assertStatus(201)
            ->assertJsonPath('project_song.notes', 'Latest owner notes');

        // Member should now have 2 versions: original + owner's copy.
        $memberVersions = ProjectSong::query()
            ->where('project_id', $this->project->id)
            ->where('user_id', $member->id)
            ->where('song_id', $song->id)
            ->count();

        expect($memberVersions)->toBe(2);
    });

    it('rejects pull when song has no source', function () {
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
        ]);

        $song = Song::factory()->create();

        $memberSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
            'song_id' => $song->id,
        ]);

        Sanctum::actingAs($member);

        $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/repertoire/{$memberSong->id}/pull-owner-copy"
        )->assertStatus(422);
    });

    it('copies charts and annotations by default when flags are omitted', function () {
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
        ]);

        $song = Song::factory()->create();

        $ownerSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song->id,
            'notes' => 'Owner v2',
        ]);
        $ownerChart = Chart::factory()->create([
            'owner_user_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'song_id' => $song->id,
            'project_song_id' => $ownerSong->id,
            'storage_disk' => 'r2',
            'storage_path_pdf' => 'charts/shared/pull-default.pdf',
            'source_sha256' => hash('sha256', 'pull-default'),
            'original_filename' => 'pull-default.pdf',
            'has_renders' => true,
            'page_count' => 1,
        ]);
        ChartRender::factory()->create([
            'chart_id' => $ownerChart->id,
            'page_number' => 1,
            'theme' => ChartTheme::Light,
            'storage_path_image' => 'charts/shared/pull-default/light/page-1.png',
        ]);
        DB::table('chart_annotation_versions')->insert([
            'chart_id' => $ownerChart->id,
            'owner_user_id' => $this->owner->id,
            'page_number' => 1,
            'local_version_id' => 'owner-annotation-1',
            'strokes' => json_encode([
                [
                    'points' => [['x' => 0.3, 'y' => 0.4]],
                    'color_value' => 4281545523,
                    'thickness' => 2.0,
                    'is_eraser' => false,
                ],
            ]),
            'client_created_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
            'song_id' => $song->id,
            'source_project_song_id' => $ownerSong->id,
        ]);

        Sanctum::actingAs($member);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/repertoire/{$memberSong->id}/pull-owner-copy"
        );

        $response->assertStatus(201);

        // The alternate should have a cloned chart with the owner's annotation carried over
        // (but rekeyed to the member as the new owner).
        $alternateId = $response->json('project_song.id');
        expect($alternateId)->not->toBeNull();

        $clonedChart = Chart::query()
            ->where('project_song_id', $alternateId)
            ->first();
        expect($clonedChart)->not->toBeNull();
        expect($clonedChart?->id)->not->toBe($ownerChart->id);

        $memberAnnotation = ChartAnnotationVersion::query()
            ->where('chart_id', $clonedChart?->id)
            ->where('owner_user_id', $member->id)
            ->first();
        expect($memberAnnotation)->not->toBeNull();
        expect($memberAnnotation?->local_version_id)->toBe('owner-annotation-1');
    });

    it('skips charts when include_charts=false', function () {
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
        ]);

        $song = Song::factory()->create();

        $ownerSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song->id,
        ]);
        Chart::factory()->create([
            'owner_user_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'song_id' => $song->id,
            'project_song_id' => $ownerSong->id,
            'storage_disk' => 'r2',
            'storage_path_pdf' => 'charts/shared/pull-no-charts.pdf',
            'source_sha256' => hash('sha256', 'pull-no-charts'),
            'original_filename' => 'pull-no-charts.pdf',
            'has_renders' => false,
            'page_count' => 1,
        ]);

        $memberSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
            'song_id' => $song->id,
            'source_project_song_id' => $ownerSong->id,
        ]);

        Sanctum::actingAs($member);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/repertoire/{$memberSong->id}/pull-owner-copy",
            [
                'include_charts' => false,
                'include_annotations' => true,
            ]
        );

        $response->assertStatus(201);
        $alternateId = $response->json('project_song.id');
        expect(
            Chart::query()->where('project_song_id', $alternateId)->count()
        )->toBe(0);
    });

    it('copies charts without annotations when include_annotations=false', function () {
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
        ]);

        $song = Song::factory()->create();

        $ownerSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song->id,
        ]);
        $ownerChart = Chart::factory()->create([
            'owner_user_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'song_id' => $song->id,
            'project_song_id' => $ownerSong->id,
            'storage_disk' => 'r2',
            'storage_path_pdf' => 'charts/shared/pull-no-ann.pdf',
            'source_sha256' => hash('sha256', 'pull-no-ann'),
            'original_filename' => 'pull-no-ann.pdf',
            'has_renders' => false,
            'page_count' => 1,
        ]);
        DB::table('chart_annotation_versions')->insert([
            'chart_id' => $ownerChart->id,
            'owner_user_id' => $this->owner->id,
            'page_number' => 1,
            'local_version_id' => 'should-not-copy',
            'strokes' => json_encode([]),
            'client_created_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberSong = ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
            'song_id' => $song->id,
            'source_project_song_id' => $ownerSong->id,
        ]);

        Sanctum::actingAs($member);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/repertoire/{$memberSong->id}/pull-owner-copy",
            [
                'include_charts' => true,
                'include_annotations' => false,
            ]
        );

        $response->assertStatus(201);

        $alternateId = $response->json('project_song.id');
        $clonedChart = Chart::query()->where('project_song_id', $alternateId)->first();
        expect($clonedChart)->not->toBeNull();
        expect(
            ChartAnnotationVersion::query()->where('chart_id', $clonedChart?->id)->count()
        )->toBe(0);
    });
});

describe('Share Setlist With Members', function () {
    it('dispatches copy jobs for all members', function () {
        Queue::fake();

        $member1 = User::factory()->create();
        $member2 = User::factory()->create();
        ProjectMember::factory()->create(['project_id' => $this->project->id, 'user_id' => $member1->id]);
        ProjectMember::factory()->create(['project_id' => $this->project->id, 'user_id' => $member2->id]);

        $setlist = Setlist::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}/share-with-members"
        );

        $response->assertSuccessful()
            ->assertJsonPath('member_count', 2);

        Queue::assertPushed(CopySetlistToMember::class, 2);
    });

    it('rejects non-owner sharing', function () {
        $member = User::factory()->create();
        ProjectMember::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $member->id,
        ]);

        $setlist = Setlist::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
        ]);

        Sanctum::actingAs($member);

        $this->postJson(
            "/api/v1/me/projects/{$this->project->id}/setlists/{$setlist->id}/share-with-members"
        )->assertStatus(403);
    });
});

describe('ProjectSongResource', function () {
    it('includes source_project_song_id and is_owner_copy', function () {
        Sanctum::actingAs($this->owner);

        $song = Song::factory()->create();
        ProjectSong::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'song_id' => $song->id,
        ]);

        $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/repertoire");

        $response->assertSuccessful()
            ->assertJsonPath('data.0.source_project_song_id', null)
            ->assertJsonPath('data.0.is_owner_copy', true);
    });
});
