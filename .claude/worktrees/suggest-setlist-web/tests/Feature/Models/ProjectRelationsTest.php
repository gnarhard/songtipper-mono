<?php

declare(strict_types=1);

use App\Enums\ProjectMemberRole;
use App\Enums\RequestStatus;
use App\Models\Project;
use App\Models\Request;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('returns performer profile image url when path is set', function () {
    Storage::fake('public');

    $project = Project::factory()->create([
        'performer_profile_image_path' => 'performer-images/test.jpg',
    ]);

    expect($project->performer_profile_image_url)->toContain('performer-images/test.jpg');
});

it('returns active requests ordered by tip then created_at', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();

    $low = Request::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'status' => RequestStatus::Active,
        'tip_amount_cents' => 500,
        'score_cents' => 500,
    ]);
    $high = Request::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'status' => RequestStatus::Active,
        'tip_amount_cents' => 2000,
        'score_cents' => 2000,
    ]);
    Request::factory()->played()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
    ]);

    $active = $project->activeRequests()->get();

    expect($active)->toHaveCount(2);
    expect($active->first()->id)->toBe($high->id);
});

it('returns played requests ordered by played_at desc', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();

    Request::factory()->played()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'played_at' => now()->subHour(),
    ]);
    $recent = Request::factory()->played()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'played_at' => now(),
    ]);

    $played = $project->playedRequests()->get();

    expect($played)->toHaveCount(2);
    expect($played->first()->id)->toBe($recent->id);
});

it('checks if user is a member', function () {
    $project = Project::factory()->create();
    $member = User::factory()->create();
    $nonMember = User::factory()->create();

    $project->addMember($member);

    expect($project->hasMember($member))->toBeTrue();
    expect($project->hasMember($nonMember))->toBeFalse();
});

it('checks if project is owned by user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    expect($project->isOwnedBy($owner))->toBeTrue();
    expect($project->isOwnedBy($other))->toBeFalse();
});

it('adds a member to project', function () {
    $project = Project::factory()->create();
    $user = User::factory()->create();

    $member = $project->addMember($user);

    expect($member->user_id)->toBe($user->id);
    expect($member->project_id)->toBe($project->id);
    expect($member->role)->toBe(ProjectMemberRole::Member);
});

it('checks if user can share setlists as owner', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $owner->id]);

    expect($project->canUserShareSetlists($owner))->toBeTrue();
});

it('checks if user can share setlists as member', function () {
    $project = Project::factory()->create();
    $member = User::factory()->create();
    $project->addMember($member, ProjectMemberRole::Member);

    expect($project->canUserShareSetlists($member))->toBeTrue();
});

it('returns highest active tip', function () {
    $project = Project::factory()->create();
    $song = Song::factory()->create();

    Request::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'status' => RequestStatus::Active,
        'tip_amount_cents' => 500,
    ]);
    Request::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song->id,
        'status' => RequestStatus::Active,
        'tip_amount_cents' => 2000,
    ]);

    expect($project->highest_active_tip)->toBe(2000);
});

it('returns highest active tip for a specific song', function () {
    $project = Project::factory()->create();
    $song1 = Song::factory()->create();
    $song2 = Song::factory()->create();

    Request::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song1->id,
        'status' => RequestStatus::Active,
        'tip_amount_cents' => 1500,
    ]);
    Request::factory()->create([
        'project_id' => $project->id,
        'song_id' => $song2->id,
        'status' => RequestStatus::Active,
        'tip_amount_cents' => 3000,
    ]);

    expect($project->highestActiveTipForSong($song1->id))->toBe(1500);
    expect($project->highestActiveTipForSong($song2->id))->toBe(3000);
});

it('returns quick tip amounts accessor with defaults', function () {
    $project = Project::factory()->create();

    expect($project->quick_tip_amounts_cents)->toBe(Project::DEFAULT_QUICK_TIP_AMOUNTS_CENTS);
});

it('returns quick tip attributes from amounts array', function () {
    $result = Project::quickTipAttributes([2500, 1500, 1000]);

    expect($result)->toBe([
        'quick_tip_1_cents' => 2500,
        'quick_tip_2_cents' => 1500,
        'quick_tip_3_cents' => 1000,
    ]);
});
