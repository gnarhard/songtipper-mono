<?php

declare(strict_types=1);

use App\Enums\RequestStatus;
use App\Models\AudienceProfile;
use App\Models\AudienceRewardClaim;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Request as SongRequest;
use App\Models\RewardThreshold;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
});

it('returns active requests in correct order', function () {
    Sanctum::actingAs($this->owner);

    $song1 = Song::factory()->create(['title' => 'Low Tip Song']);
    $song2 = Song::factory()->create(['title' => 'High Tip Song']);
    $song3 = Song::factory()->create(['title' => 'Medium Tip Song']);

    // Create requests with different tips (higher tip should come first)
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song1->id,
        'tip_amount_cents' => 500,
        'score_cents' => 500,
        'created_at' => now()->subMinutes(10),
    ]);
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song2->id,
        'tip_amount_cents' => 2000,
        'score_cents' => 2000,
        'created_at' => now()->subMinutes(5),
    ]);
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song3->id,
        'tip_amount_cents' => 1000,
        'score_cents' => 1000,
        'created_at' => now()->subMinutes(1),
    ]);
    SongRequest::factory()->played()->create([
        'project_id' => $this->project->id,
        'song_id' => $song2->id,
        'tip_amount_cents' => 4000,
        'score_cents' => 4000,
        'created_at' => now()->subDay(),
        'played_at' => now()->subDay(),
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.daily_record_event', null);

    $data = $response->json('data');
    expect($data[0]['tip_amount_cents'])->toBe(2000);
    expect($data[1]['tip_amount_cents'])->toBe(1000);
    expect($data[2]['tip_amount_cents'])->toBe(500);
});

it('orders requests by created_at when tips are equal', function () {
    Sanctum::actingAs($this->owner);

    $song1 = Song::factory()->create(['title' => 'First Song']);
    $song2 = Song::factory()->create(['title' => 'Second Song']);

    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song1->id,
        'tip_amount_cents' => 1000,
        'score_cents' => 1000,
        'created_at' => now()->subMinutes(10),
    ]);
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song2->id,
        'tip_amount_cents' => 1000,
        'score_cents' => 1000,
        'created_at' => now()->subMinutes(5),
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data[0]['song']['title'])->toBe('First Song');
    expect($data[1]['song']['title'])->toBe('Second Song');
});

it('only returns active requests', function () {
    Sanctum::actingAs($this->owner);

    $activeSong = Song::factory()->create(['title' => 'Active Song']);
    $playedSong = Song::factory()->create(['title' => 'Played Song']);

    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $activeSong->id,
    ]);
    SongRequest::factory()->played()->create([
        'project_id' => $this->project->id,
        'song_id' => $playedSong->id,
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.song.title', 'Active Song');
});

it('returns 304 when etag matches', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);

    // First request to get the ETag
    $firstResponse = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");
    $etag = $firstResponse->headers->get('ETag');

    // Second request with matching ETag
    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'If-None-Match' => $etag,
    ]);

    $response->assertStatus(304);
});

it('returns new data when etag does not match', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'If-None-Match' => '"old-etag"',
    ]);

    $response->assertSuccessful()
        ->assertHeader('ETag');
});

it('returns played requests history', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create(['title' => 'Played Song']);
    SongRequest::factory()->played()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'played_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/requests/history");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.song.title', 'Played Song');
});

it('returns correct response structure for each request', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create(['title' => 'Test Song', 'artist' => 'Test Artist']);
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 1000,
        'score_cents' => 1000,
        'note' => 'Please play this!',
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertSuccessful();

    $item = $response->json('data.0');
    expect($item)->toHaveKeys(['id', 'song', 'tip_amount_cents', 'tip_amount_dollars', 'status', 'requester_name', 'note', 'played_at', 'created_at']);
    expect($response->json())->toHaveKey('meta');
    expect($item['song'])->toHaveKeys(['id', 'title', 'artist']);
    expect($item['song']['title'])->toBe('Test Song');
    expect($item['song']['artist'])->toBe('Test Artist');
    expect($item['status'])->toBe('active');
});

it('includes requester_name from audience profile in queue response', function () {
    Sanctum::actingAs($this->owner);

    $audienceProfile = AudienceProfile::factory()->create([
        'project_id' => $this->project->id,
        'display_name' => 'Jane Smith',
    ]);

    $song = Song::factory()->create(['title' => 'Named Request Song']);
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'audience_profile_id' => $audienceProfile->id,
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertSuccessful()
        ->assertJsonPath('data.0.requester_name', 'Jane Smith');
});

it('returns null requester_name for manual queue items', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'custom',
        'custom_title' => 'No Audience Request',
    ]);

    $response->assertCreated()
        ->assertJsonPath('request.requester_name', null);
});

it('includes the daily record event in queue metadata and updates the etag', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create(['title' => 'Record Day']);
    SongRequest::factory()->played()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 1000,
        'score_cents' => 1000,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
        'played_at' => now()->subDay(),
        'status' => RequestStatus::Played,
    ]);
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 1500,
        'score_cents' => 1500,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $firstResponse = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/queue?timezone=America/Denver"
    );

    $firstResponse->assertSuccessful()
        ->assertJsonPath('meta.daily_record_event.gross_tip_amount_cents', 1500)
        ->assertJsonPath(
            'meta.daily_record_event.local_date',
            now()->setTimezone('America/Denver')->toDateString(),
        )
        ->assertJsonPath('meta.daily_record_event.timezone', 'America/Denver');

    $firstEtag = $firstResponse->headers->get('ETag');

    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 800,
        'score_cents' => 800,
        'created_at' => now()->addMinute(),
        'updated_at' => now()->addMinute(),
    ]);

    // Flush the short-lived daily-record-event cache so the second poll
    // picks up the newly added tip.
    Cache::forget(sprintf(
        'project:%d:daily-record-event:%s',
        $this->project->id,
        now('America/Denver')->toDateString(),
    ));

    $secondResponse = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/queue?timezone=America/Denver"
    );

    $secondResponse->assertSuccessful()
        ->assertJsonPath('meta.daily_record_event.gross_tip_amount_cents', 2300);

    expect($secondResponse->headers->get('ETag'))->not->toBe($firstEtag);
});

it('allows performer to manually add a custom queue item', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'custom',
        'custom_title' => 'Crowd Favorite Mashup',
        'note' => 'Mash two choruses if possible',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Queue item added.')
        ->assertJsonPath('request.song.title', 'Crowd Favorite Mashup')
        ->assertJsonPath('request.song.artist', 'Custom Request')
        ->assertJsonPath('request.tip_amount_cents', 0)
        ->assertJsonPath('request.status', 'active');

    $requestId = $response->json('request.id');
    $stored = SongRequest::query()->findOrFail($requestId);

    expect($stored->project_id)->toBe($this->project->id);
    expect($stored->tip_amount_cents)->toBe(0);
    expect($stored->score_cents)->toBe(0);
    expect($stored->payment_provider)->toBe('none');
});

it('stores manual tip amount when performer provides one', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'custom',
        'custom_title' => 'Big spender request',
        'tip_amount_cents' => 1250,
    ]);

    $response->assertCreated()
        ->assertJsonPath('request.tip_amount_cents', 1300)
        ->assertJsonPath('request.tip_amount_dollars', '13');

    $requestId = $response->json('request.id');
    $stored = SongRequest::query()->findOrFail($requestId);

    expect($stored->tip_amount_cents)->toBe(1300);
    expect($stored->score_cents)->toBe(1300);
});

it('allows performer to manually add a song from project repertoire', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create(['title' => 'Neon Moon', 'artist' => 'Brooks & Dunn']);
    $this->project->projectSongs()->create([
        'song_id' => $song->id,
    ]);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'repertoire_song',
        'song_id' => $song->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('request.song.id', $song->id)
        ->assertJsonPath('request.song.title', 'Neon Moon')
        ->assertJsonPath('request.status', 'active');
});

it('defaults manual queue tip to zero when omitted', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    $this->project->projectSongs()->create([
        'song_id' => $song->id,
    ]);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'repertoire_song',
        'song_id' => $song->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('request.tip_amount_cents', 0)
        ->assertJsonPath('request.tip_amount_dollars', '0');
});

it('validates repertoire song belongs to the selected project for manual queue add', function () {
    Sanctum::actingAs($this->owner);

    $otherProject = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $song = Song::factory()->create();
    $otherProject->projectSongs()->create([
        'song_id' => $song->id,
    ]);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'repertoire_song',
        'song_id' => $song->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['song_id']);
});

it('allows performer to manually add an original when originals are enabled', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'original',
    ]);

    $response->assertCreated()
        ->assertJsonPath('request.song.title', Song::ORIGINAL_REQUEST_TITLE)
        ->assertJsonPath('request.song.artist', Song::ORIGINAL_REQUEST_ARTIST);
});

it('rejects manual original queue item when originals are disabled', function () {
    Sanctum::actingAs($this->owner);
    $this->project->update(['is_accepting_original_requests' => false]);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'original',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'This project is not currently accepting original requests.');
});

it('requires authentication for queue', function () {
    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertUnauthorized();
});

it('requires authentication for history', function () {
    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/requests/history");

    $response->assertUnauthorized();
});

it('requires authentication for manual queue add', function () {
    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'custom',
        'custom_title' => 'Unplugged moment',
    ]);

    $response->assertUnauthorized();
});

it('returns 404 for project not owned by user', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertNotFound();
});

it('returns 404 when user cannot manually add queue items for a project', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'custom',
        'custom_title' => 'Late Night Jam',
    ]);

    $response->assertNotFound();
});

it('allows a basic-plan project member to view the queue', function () {
    $member = User::factory()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);
    $this->project->addMember($member);

    $song = Song::factory()->create(['title' => 'Member Queue Song']);
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 1500,
        'score_cents' => 1500,
    ]);

    Sanctum::actingAs($member);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.song.title', 'Member Queue Song');
});

it('marks request as played', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'performance_count' => 4,
        'last_performed_at' => now()->subDay(),
    ]);
    $songRequest = SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);

    $response = $this->postJson("/api/v1/me/requests/{$songRequest->id}/played");

    $response->assertSuccessful()
        ->assertJsonPath('request.status', 'played');

    $songRequest->refresh();
    $projectSong->refresh();

    expect($songRequest->status)->toBe(RequestStatus::Played);
    expect($songRequest->played_at)->not->toBeNull();
    expect($projectSong->performance_count)->toBe(5);
    expect($projectSong->last_performed_at)->not->toBeNull();
});

it('does not increment repertoire performance stats twice for the same request', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    $projectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'performance_count' => 4,
    ]);
    $songRequest = SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);

    $this->postJson("/api/v1/me/requests/{$songRequest->id}/played")
        ->assertSuccessful();
    $this->postJson("/api/v1/me/requests/{$songRequest->id}/played")
        ->assertSuccessful();

    $projectSong->refresh();
    expect($projectSong->performance_count)->toBe(5);
});

it('only allows project owner or member to mark requests as played', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $song = Song::factory()->create();
    $songRequest = SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);

    $response = $this->postJson("/api/v1/me/requests/{$songRequest->id}/played");

    $response->assertForbidden();
});

it('trims note to null when note is empty whitespace in manual queue add', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'custom',
        'custom_title' => 'Trimmed Note Song',
        'note' => '   ',
    ]);

    $response->assertCreated();

    $requestId = $response->json('request.id');
    $stored = SongRequest::query()->findOrFail($requestId);
    expect($stored->note)->toBeNull();
});

it('falls back to UTC for invalid timezone in queue index', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
    ]);

    $response = $this->getJson(
        "/api/v1/me/projects/{$this->project->id}/queue?timezone=Invalid/Timezone"
    );

    $response->assertSuccessful();
});

it('returns 404 for history when user has no access to project', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/requests/history");

    $response->assertNotFound();
});

// ---- is_manual field ----

it('returns is_manual true for manual queue items and false for stripe items', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create(['title' => 'Manual Check']);
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'payment_provider' => 'none',
    ]);
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'payment_provider' => 'stripe',
        'payment_intent_id' => 'pi_is_manual_test',
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertSuccessful();
    $data = $response->json('data');
    $manualItem = collect($data)->firstWhere('is_manual', true);
    $stripeItem = collect($data)->firstWhere('is_manual', false);
    expect($manualItem)->not->toBeNull();
    expect($stripeItem)->not->toBeNull();
});

it('includes is_manual in manual queue add response', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/queue", [
        'type' => 'custom',
        'custom_title' => 'Is Manual Check',
    ]);

    $response->assertCreated()
        ->assertJsonPath('request.is_manual', true);
});

// ---- PATCH queue item ----

it('updates tip on a manual active queue item', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    $request = SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 500,
        'score_cents' => 500,
        'payment_provider' => 'none',
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/queue/{$request->id}",
        ['tip_amount_cents' => 1000],
    );

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Queue item updated.')
        ->assertJsonPath('request.tip_amount_cents', 1000)
        ->assertJsonPath('request.is_manual', true);

    $request->refresh();
    expect($request->tip_amount_cents)->toBe(1000);
    expect($request->score_cents)->toBe(1000);
});

it('normalizes tip amount cents to whole dollars on patch', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    $request = SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'payment_provider' => 'none',
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/queue/{$request->id}",
        ['tip_amount_cents' => 1250],
    );

    $response->assertSuccessful()
        ->assertJsonPath('request.tip_amount_cents', 1300);
});

it('rejects patch on stripe queue item with 403', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    $request = SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'payment_provider' => 'stripe',
        'payment_intent_id' => 'pi_patch_guard',
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/queue/{$request->id}",
        ['tip_amount_cents' => 1000],
    );

    $response->assertForbidden()
        ->assertJsonPath('message', 'Only manual queue items can be edited.');
});

it('rejects patch on played queue item with 422', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    $request = SongRequest::factory()->played()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'payment_provider' => 'none',
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/queue/{$request->id}",
        ['tip_amount_cents' => 1000],
    );

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Only active queue items can be edited.');
});

it('rejects patch without tip_amount_cents', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    $request = SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'payment_provider' => 'none',
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/queue/{$request->id}",
        [],
    );

    $response->assertUnprocessable();
});

it('rejects patch with negative tip_amount_cents', function () {
    Sanctum::actingAs($this->owner);

    $song = Song::factory()->create();
    $request = SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'payment_provider' => 'none',
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/queue/{$request->id}",
        ['tip_amount_cents' => -100],
    );

    $response->assertUnprocessable();
});

it('returns 404 when patching request from another project', function () {
    Sanctum::actingAs($this->owner);

    $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
    $song = Song::factory()->create();
    $request = SongRequest::factory()->active()->create([
        'project_id' => $otherProject->id,
        'song_id' => $song->id,
        'payment_provider' => 'none',
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/queue/{$request->id}",
        ['tip_amount_cents' => 1000],
    );

    $response->assertNotFound();
});

it('returns 404 when non-member patches a queue item', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $song = Song::factory()->create();
    $request = SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'payment_provider' => 'none',
    ]);

    $response = $this->patchJson(
        "/api/v1/me/projects/{$this->project->id}/queue/{$request->id}",
        ['tip_amount_cents' => 1000],
    );

    $response->assertNotFound();
});

// --- Pending physical reward claims in queue meta ---

it('includes pending physical reward claims in queue meta', function () {
    Sanctum::actingAs($this->owner);

    $threshold = RewardThreshold::factory()
        ->withType('free_cd', 'Free CD')
        ->create([
            'project_id' => $this->project->id,
            'reward_icon' => 'album',
            'reward_description' => 'Come up after the show.',
            'threshold_cents' => 4000,
        ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $this->project->id,
        'display_name' => 'Big Tipper',
        'cumulative_tip_cents' => 4000,
    ]);

    $pendingClaim = AudienceRewardClaim::factory()->pending()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);
    AudienceRewardClaim::factory()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'meta.pending_rewards')
        ->assertJsonPath('meta.pending_rewards.0.id', $pendingClaim->id)
        ->assertJsonPath('meta.pending_rewards.0.reward_threshold_id', $threshold->id)
        ->assertJsonPath('meta.pending_rewards.0.reward_label', 'Free CD')
        ->assertJsonPath('meta.pending_rewards.0.reward_icon', 'album')
        ->assertJsonPath('meta.pending_rewards.0.reward_icon_emoji', '💿')
        ->assertJsonPath('meta.pending_rewards.0.reward_description', 'Come up after the show.')
        ->assertJsonPath('meta.pending_rewards.0.audience_display_name', 'Big Tipper');
});

it('orders pending rewards oldest first in queue meta', function () {
    Sanctum::actingAs($this->owner);

    $threshold = RewardThreshold::factory()
        ->withType('free_cd', 'Free CD')
        ->create(['project_id' => $this->project->id]);
    $profile = AudienceProfile::factory()->create(['project_id' => $this->project->id]);

    $oldest = AudienceRewardClaim::factory()->pending()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
        'created_at' => now()->subMinutes(10),
    ]);
    $newest = AudienceRewardClaim::factory()->pending()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
        'created_at' => now(),
    ]);
    $middle = AudienceRewardClaim::factory()->pending()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
        'created_at' => now()->subMinutes(5),
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'meta.pending_rewards')
        ->assertJsonPath('meta.pending_rewards.0.id', $oldest->id)
        ->assertJsonPath('meta.pending_rewards.1.id', $middle->id)
        ->assertJsonPath('meta.pending_rewards.2.id', $newest->id);
});

it('excludes pending rewards from other projects in queue meta', function () {
    Sanctum::actingAs($this->owner);

    $otherProject = Project::factory()->create(['owner_user_id' => $this->owner->id]);
    $otherThreshold = RewardThreshold::factory()
        ->withType('free_cd', 'Free CD')
        ->create(['project_id' => $otherProject->id]);
    $otherProfile = AudienceProfile::factory()->create(['project_id' => $otherProject->id]);

    AudienceRewardClaim::factory()->pending()->create([
        'audience_profile_id' => $otherProfile->id,
        'reward_threshold_id' => $otherThreshold->id,
    ]);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");

    $response->assertSuccessful()
        ->assertJsonCount(0, 'meta.pending_rewards');
});

it('invalidates the queue etag when a new pending reward arrives', function () {
    Sanctum::actingAs($this->owner);

    $threshold = RewardThreshold::factory()
        ->withType('free_cd', 'Free CD')
        ->create(['project_id' => $this->project->id]);
    $profile = AudienceProfile::factory()->create(['project_id' => $this->project->id]);

    $firstResponse = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");
    $firstResponse->assertSuccessful()
        ->assertJsonCount(0, 'meta.pending_rewards');
    $firstEtag = $firstResponse->headers->get('ETag');

    AudienceRewardClaim::factory()->pending()->create([
        'audience_profile_id' => $profile->id,
        'reward_threshold_id' => $threshold->id,
    ]);

    $secondResponse = $this->getJson("/api/v1/me/projects/{$this->project->id}/queue");
    $secondResponse->assertSuccessful()
        ->assertJsonCount(1, 'meta.pending_rewards');

    expect($secondResponse->headers->get('ETag'))->not->toBe($firstEtag);
});
