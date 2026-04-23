<?php

declare(strict_types=1);

use App\Jobs\SyncRepertoireToMember;
use App\Models\Project;
use App\Models\User;
use App\Services\MemberRepertoireSyncService;
use Mockery\MockInterface;

it('copies all songs and setlists to the member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    $this->mock(MemberRepertoireSyncService::class, function (MockInterface $mock) use ($project, $member) {
        $mock->shouldReceive('copyAllSongsToMember')
            ->once()
            ->withArgs(fn (Project $p, User $m) => $p->id === $project->id && $m->id === $member->id);

        $mock->shouldReceive('copyAllSetlistsToMember')
            ->once()
            ->withArgs(fn (Project $p, User $m) => $p->id === $project->id && $m->id === $member->id);
    });

    (new SyncRepertoireToMember($project->id, $member->id))->handle(
        app(MemberRepertoireSyncService::class)
    );
});

it('does nothing when the project does not exist', function () {
    $member = User::factory()->create();

    $this->mock(MemberRepertoireSyncService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('copyAllSongsToMember');
        $mock->shouldNotReceive('copyAllSetlistsToMember');
    });

    (new SyncRepertoireToMember(999999, $member->id))->handle(
        app(MemberRepertoireSyncService::class)
    );
});

it('does nothing when the member does not exist', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    $this->mock(MemberRepertoireSyncService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('copyAllSongsToMember');
        $mock->shouldNotReceive('copyAllSetlistsToMember');
    });

    (new SyncRepertoireToMember($project->id, 999999))->handle(
        app(MemberRepertoireSyncService::class)
    );
});
