<?php

declare(strict_types=1);

use App\Jobs\CopySetlistToMember;
use App\Models\Project;
use App\Models\Setlist;
use App\Models\User;
use App\Services\MemberRepertoireSyncService;
use Mockery\MockInterface;

it('copies the setlist to the member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $setlist = Setlist::factory()->create(['project_id' => $project->id]);

    $this->mock(MemberRepertoireSyncService::class, function (MockInterface $mock) use ($setlist, $member) {
        $mock->shouldReceive('copySetlistToMember')
            ->once()
            ->withArgs(fn (Setlist $s, User $m) => $s->id === $setlist->id && $m->id === $member->id);
    });

    (new CopySetlistToMember($setlist->id, $member->id))->handle(
        app(MemberRepertoireSyncService::class)
    );
});

it('does nothing when the setlist does not exist', function () {
    $member = User::factory()->create();

    $this->mock(MemberRepertoireSyncService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('copySetlistToMember');
    });

    (new CopySetlistToMember(999999, $member->id))->handle(
        app(MemberRepertoireSyncService::class)
    );
});

it('does nothing when the member does not exist', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $setlist = Setlist::factory()->create(['project_id' => $project->id]);

    $this->mock(MemberRepertoireSyncService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('copySetlistToMember');
    });

    (new CopySetlistToMember($setlist->id, 999999))->handle(
        app(MemberRepertoireSyncService::class)
    );
});
