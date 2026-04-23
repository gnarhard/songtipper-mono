<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Setlist;
use App\Models\SetlistSet;
use App\Models\SetlistShareLink;
use App\Models\SetlistSong;
use App\Models\User;

it('displays the shared setlist page with setlist name and project name', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'The Jazz Trio',
    ]);
    $setlist = Setlist::factory()->create([
        'project_id' => $project->id,
        'name' => 'Friday Night Gig',
    ]);
    $shareLink = SetlistShareLink::query()->create([
        'project_id' => $project->id,
        'setlist_id' => $setlist->id,
        'created_by_user_id' => $owner->id,
        'token' => 'test-shared-setlist-token',
    ]);

    $response = $this->get("/shared-setlists/{$shareLink->token}");

    $response->assertOk()
        ->assertSee('Friday Night Gig')
        ->assertSee('The Jazz Trio')
        ->assertSee('Add to Your Project')
        ->assertSee('songtipper:///shared-setlists/test-shared-setlist-token', false);
});

it('displays sets and songs in the shared setlist', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
    ]);
    $setlist = Setlist::factory()->create([
        'project_id' => $project->id,
    ]);

    $set = SetlistSet::factory()->create([
        'setlist_id' => $setlist->id,
        'name' => 'Set 1',
        'order_index' => 0,
    ]);

    $projectSong = ProjectSong::factory()->create([
        'project_id' => $project->id,
        'title' => 'Fly Me to the Moon',
        'artist' => 'Frank Sinatra',
    ]);

    SetlistSong::factory()->create([
        'set_id' => $set->id,
        'project_song_id' => $projectSong->id,
        'order_index' => 0,
    ]);

    $shareLink = SetlistShareLink::query()->create([
        'project_id' => $project->id,
        'setlist_id' => $setlist->id,
        'created_by_user_id' => $owner->id,
        'token' => 'songs-display-token',
    ]);

    $response = $this->get("/shared-setlists/{$shareLink->token}");

    $response->assertOk()
        ->assertSee('Set 1')
        ->assertSee('Fly Me to the Moon')
        ->assertSee('Frank Sinatra');
});

it('displays note entries in the shared setlist', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
    ]);
    $setlist = Setlist::factory()->create([
        'project_id' => $project->id,
    ]);

    $set = SetlistSet::factory()->create([
        'setlist_id' => $setlist->id,
        'name' => 'Set 1',
        'order_index' => 0,
    ]);

    SetlistSong::factory()->create([
        'set_id' => $set->id,
        'project_song_id' => null,
        'order_index' => 0,
        'notes' => 'Take a 15 minute break',
    ]);

    $shareLink = SetlistShareLink::query()->create([
        'project_id' => $project->id,
        'setlist_id' => $setlist->id,
        'created_by_user_id' => $owner->id,
        'token' => 'notes-display-token',
    ]);

    $response = $this->get("/shared-setlists/{$shareLink->token}");

    $response->assertOk()
        ->assertSee('Take a 15 minute break');
});

it('shows the add to project CTA at top and bottom', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
    ]);
    $setlist = Setlist::factory()->create([
        'project_id' => $project->id,
    ]);
    $shareLink = SetlistShareLink::query()->create([
        'project_id' => $project->id,
        'setlist_id' => $setlist->id,
        'created_by_user_id' => $owner->id,
        'token' => 'cta-test-token',
    ]);

    $response = $this->get("/shared-setlists/{$shareLink->token}");

    $content = $response->getContent();
    $response->assertOk();

    // Verify the CTA text appears at least twice (top and bottom)
    expect(substr_count($content, 'Add to Your Project'))->toBeGreaterThanOrEqual(2);
});
