<?php

declare(strict_types=1);

use App\Jobs\FanOutSongToMembers;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;
use App\Services\MemberRepertoireSyncService;
use Mockery\MockInterface;

it('copies a song to all project members', function () {
    $owner = User::factory()->create();
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $member1->id,
    ]);
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $member2->id,
    ]);

    $song = Song::factory()->create();
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
    ]);

    $this->mock(MemberRepertoireSyncService::class, function (MockInterface $mock) {
        $mock->shouldReceive('copySongToMember')->twice();
    });

    (new FanOutSongToMembers($projectSong->id))->handle(
        app(MemberRepertoireSyncService::class)
    );
});

it('does nothing when project song does not exist', function () {
    $this->mock(MemberRepertoireSyncService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('copySongToMember');
    });

    (new FanOutSongToMembers(999999))->handle(
        app(MemberRepertoireSyncService::class)
    );
});

it('does nothing when project has no members', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $song = Song::factory()->create();
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
    ]);

    $this->mock(MemberRepertoireSyncService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('copySongToMember');
    });

    (new FanOutSongToMembers($projectSong->id))->handle(
        app(MemberRepertoireSyncService::class)
    );
});
