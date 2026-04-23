<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistShareAcceptance;
use App\Models\SetlistShareLink;
use App\Models\User;
use App\Services\SetlistShareService;

it('re-copies setlist when previous copied setlist was deleted', function () {
    $owner = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $setlist = Setlist::factory()->create(['project_id' => $project->id, 'name' => 'Original']);
    SetlistSet::factory()->create(['setlist_id' => $setlist->id, 'order_index' => 0]);

    $shareLink = SetlistShareLink::factory()->create([
        'project_id' => $project->id,
        'setlist_id' => $setlist->id,
        'created_by_user_id' => $owner->id,
    ]);

    $acceptingUser = User::factory()->create();

    // Create a real setlist, then create acceptance pointing to it, then delete the setlist
    $tempSetlist = Setlist::factory()->create(['project_id' => $project->id]);
    $acceptance = SetlistShareAcceptance::factory()->create([
        'setlist_share_link_id' => $shareLink->id,
        'user_id' => $acceptingUser->id,
        'copied_setlist_id' => $tempSetlist->id,
    ]);
    $tempSetlist->delete();

    $service = new SetlistShareService;
    $result = $service->acceptLink($shareLink, $acceptingUser);

    expect($result->id)->toBe($acceptance->id)
        ->and($result->copied_setlist_id)->not->toBe(99999);
});

it('appends Copy suffix to setlist name', function () {
    $owner = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $setlist = Setlist::factory()->create([
        'project_id' => $project->id,
        'name' => 'My Setlist',
    ]);
    SetlistSet::factory()->create(['setlist_id' => $setlist->id, 'order_index' => 0]);

    $shareLink = SetlistShareLink::factory()->create([
        'project_id' => $project->id,
        'setlist_id' => $setlist->id,
        'created_by_user_id' => $owner->id,
    ]);

    $acceptingUser = User::factory()->create();

    $service = new SetlistShareService;
    $result = $service->acceptLink($shareLink, $acceptingUser);

    $copiedSetlist = Setlist::find($result->copied_setlist_id);
    expect($copiedSetlist->name)->toBe('My Setlist (Copy)');
});

it('does not double-append Copy suffix', function () {
    $owner = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $setlist = Setlist::factory()->create([
        'project_id' => $project->id,
        'name' => 'My Setlist (Copy)',
    ]);
    SetlistSet::factory()->create(['setlist_id' => $setlist->id, 'order_index' => 0]);

    $shareLink = SetlistShareLink::factory()->create([
        'project_id' => $project->id,
        'setlist_id' => $setlist->id,
        'created_by_user_id' => $owner->id,
    ]);

    $acceptingUser = User::factory()->create();

    $service = new SetlistShareService;
    $result = $service->acceptLink($shareLink, $acceptingUser);

    $copiedSetlist = Setlist::find($result->copied_setlist_id);
    expect($copiedSetlist->name)->toBe('My Setlist (Copy)');
});

it('uses default name for empty setlist name', function () {
    $owner = billingReadyUser();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);
    $setlist = Setlist::factory()->create([
        'project_id' => $project->id,
        'name' => '',
    ]);
    SetlistSet::factory()->create(['setlist_id' => $setlist->id, 'order_index' => 0]);

    $shareLink = SetlistShareLink::factory()->create([
        'project_id' => $project->id,
        'setlist_id' => $setlist->id,
        'created_by_user_id' => $owner->id,
    ]);

    $acceptingUser = User::factory()->create();

    $service = new SetlistShareService;
    $result = $service->acceptLink($shareLink, $acceptingUser);

    $copiedSetlist = Setlist::find($result->copied_setlist_id);
    expect($copiedSetlist->name)->toBe('Shared Setlist Copy');
});
