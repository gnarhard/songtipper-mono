<?php

declare(strict_types=1);

use App\Models\AudienceProfile;
use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\Song;
use App\Models\User;
use App\Models\UserPayoutAccount;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('creates an audience profile for free public requests without awarding achievements', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    UserPayoutAccount::query()->create([
        'user_id' => $owner->id,
        'stripe_account_id' => 'acct_achievements_owner_1',
        'status' => UserPayoutAccount::STATUS_ENABLED,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);
    $song = Song::factory()->create();

    $response = $this
        ->withCookie('songtipper_audience_token', 'aud-test-free-tip')
        ->postJson("/api/v1/public/projects/{$project->slug}/requests", [
            'song_id' => $song->id,
            'tip_amount_cents' => 0,
            'note' => 'please and thank you',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('requires_payment', false)
        ->assertJsonPath('payment_intent_id', null);

    $songRequest = SongRequest::query()->firstOrFail();

    expect($songRequest->audience_profile_id)->not->toBeNull();
    $this->assertDatabaseEmpty('audience_achievements');
});

it('does not award achievements when performer marks a request as played', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
    ]);
    $song = Song::factory()->create();
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'visitor_token' => 'aud-test-whisperer',
    ]);

    $songRequest = SongRequest::query()->create([
        'project_id' => $project->id,
        'audience_profile_id' => $profile->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 1500,
        'score_cents' => 1500,
        'status' => 'active',
        'note' => null,
        'requested_from_ip' => '127.0.0.1',
        'payment_provider' => 'none',
        'payment_intent_id' => null,
    ]);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/me/requests/{$songRequest->id}/played")
        ->assertSuccessful();

    $this->assertDatabaseEmpty('audience_achievements');
});

it('redirects to the performer info page without creating audience feature data', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'performer_info_url' => 'https://example.com/artist',
    ]);

    $response = $this
        ->withCookie('songtipper_audience_token', 'aud-test-lore')
        ->get(route('project.learn-more', ['projectSlug' => $project->slug]));

    $response->assertRedirect('https://example.com/artist');

    $this->assertDatabaseEmpty('audience_profiles');
    $this->assertDatabaseEmpty('audience_achievements');
});

it('removes the public audience profile and leaderboard endpoints', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/v1/public/projects/{$project->slug}/audience/me")
        ->assertNotFound();

    $this->getJson("/api/v1/public/projects/{$project->slug}/audience/leaderboard")
        ->assertNotFound();
});

it('does not show legacy achievement toasts after request confirmation redirects to the repertoire page', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
    ]);
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'visitor_token' => 'aud-test-confirmation',
    ]);
    $song = Song::factory()->create();

    DB::table('audience_achievements')->insert([
        'audience_profile_id' => $profile->id,
        'project_id' => $project->id,
        'request_id' => null,
        'code' => 'lorekeeper',
        'title' => 'Lorekeeper',
        'description' => 'You learned more about the performer.',
        'earned_at' => now(),
        'notified_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    SongRequest::factory()->active()->create([
        'project_id' => $project->id,
        'audience_profile_id' => $profile->id,
        'song_id' => $song->id,
        'tip_amount_cents' => 2000,
        'score_cents' => 2000,
        'payment_intent_id' => 'pi_test_confirmation_achievement',
    ]);

    $response = $this
        ->followingRedirects()
        ->withCookie('songtipper_audience_token', 'aud-test-confirmation')
        ->get(route('request.confirmation', [
            'redirect_status' => 'succeeded',
            'project_slug' => $project->slug,
            'submission' => 'request',
            'payment_intent' => 'pi_test_confirmation_achievement',
        ]));

    $response->assertSuccessful()
        ->assertSee("Request submitted. You're currently #1 in the queue.", false)
        ->assertSee('Your Requests')
        ->assertSee('In queue')
        ->assertSee('#1')
        ->assertDontSee('Achievement Unlocked')
        ->assertDontSee('Lorekeeper')
        ->assertSee('min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse', false)
        ->assertSee('rounded-2xl border border-ink-border/80 bg-surface/95 shadow-sm backdrop-blur-sm', false)
        ->assertSee('bg-surface-muted px-4 py-3 hover:bg-surface transition', false)
        ->assertDontSee('bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100', false)
        ->assertDontSee('border border-emerald-300 bg-emerald-50 p-4', false)
        ->assertDontSee('text-sm font-semibold text-emerald-800 dark:text-emerald-200', false);
});
