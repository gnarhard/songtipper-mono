<?php

declare(strict_types=1);

use App\Enums\RequestStatus;
use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\Song;
use App\Models\User;
use App\Services\PaymentService;
use Stripe\PaymentIntent as StripePaymentIntent;

it('redirects successful paid song requests back to the repertoire page with queue position flash data', function () {
    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_confirmation_redirect',
        'country' => 'US',
        'default_currency' => 'usd',
        'details_submitted' => true,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
        'requirements_disabled_reason' => null,
        'status' => 'enabled',
        'status_reason' => null,
        'last_synced_at' => now(),
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'request-confirmation-project',
    ]);
    $song = Song::factory()->create([
        'title' => 'Returned Request Song',
    ]);

    SongRequest::factory()->active()->create([
        'project_id' => $project->id,
        'song_id' => Song::factory()->create()->id,
        'tip_amount_cents' => 5000,
        'score_cents' => 5000,
        'created_at' => now()->subMinute(),
    ]);

    $projectId = $project->id;
    $songId = $song->id;

    app()->instance(PaymentService::class, new class($projectId, $songId) extends PaymentService
    {
        public function __construct(
            private int $projectId,
            private int $songId,
        ) {}

        public function retrievePaymentIntentWithExpand(string $paymentIntentId, ?string $stripeAccountId = null, array $expand = []): StripePaymentIntent
        {
            expect($paymentIntentId)->toBe('pi_confirmation_redirect');
            expect($stripeAccountId)->toBe('acct_confirmation_redirect');

            return StripePaymentIntent::constructFrom([
                'id' => 'pi_confirmation_redirect',
                'object' => 'payment_intent',
                'status' => 'succeeded',
                'amount' => 2000,
                'amount_received' => 2000,
                'metadata' => [
                    'project_id' => (string) $this->projectId,
                    'song_id' => (string) $this->songId,
                    'note' => 'Play it soon',
                    'requested_from_ip' => '127.0.0.1',
                ],
            ]);
        }
    });

    $response = $this->get('/request/confirmation?redirect_status=succeeded&submission=request&project_slug='
        .$project->slug.'&payment_intent=pi_confirmation_redirect');

    $response->assertRedirect(route('project.page', ['projectSlug' => $project->slug]))
        ->assertSessionHas('request_success.message', "Request submitted. You're currently #2 in the queue.")
        ->assertSessionHas('request_success.queue_position', 2);

    $songRequest = SongRequest::query()
        ->where('payment_intent_id', 'pi_confirmation_redirect')
        ->first();

    expect($songRequest)->not->toBeNull();
    expect(session('request_success.request_id'))->toBe($songRequest->id);
    expect($songRequest->project_id)->toBe($project->id);
    expect($songRequest->song_id)->toBe($song->id);
    expect($songRequest->tip_amount_cents)->toBe(2000);
    expect($songRequest->status)->toBe(RequestStatus::Active);
});

it('renders the confirmation view when redirect_status is not succeeded', function () {
    $response = $this->get('/request/confirmation?redirect_status=failed');

    $response->assertOk()
        ->assertViewIs('pages.confirmation');
});

it('renders the confirmation view when submission is not request or tip', function () {
    $response = $this->get('/request/confirmation?redirect_status=succeeded&submission=other');

    $response->assertOk()
        ->assertViewIs('pages.confirmation');
});

it('renders the confirmation view when project_slug is empty', function () {
    $response = $this->get('/request/confirmation?redirect_status=succeeded&submission=request&project_slug=');

    $response->assertOk()
        ->assertViewIs('pages.confirmation');
});

it('redirects to project page without flash when payment_intent is missing', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'no-payment-project',
    ]);

    $response = $this->get('/request/confirmation?redirect_status=succeeded&submission=request&project_slug='
        .$project->slug);

    $response->assertRedirect(route('project.page', ['projectSlug' => $project->slug]));
    $response->assertSessionMissing('request_success');
});

it('redirects to project page when payment_intent_id matches no request and payout account is missing', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'no-payout-project',
    ]);

    $response = $this->get('/request/confirmation?redirect_status=succeeded&submission=request&project_slug='
        .$project->slug.'&payment_intent=pi_nonexistent');

    $response->assertRedirect(route('project.page', ['projectSlug' => $project->slug]));
    $response->assertSessionMissing('request_success');
});

it('redirects to project page when resolved request belongs to different project', function () {
    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_diff_project',
        'country' => 'US',
        'default_currency' => 'usd',
        'details_submitted' => true,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
        'requirements_disabled_reason' => null,
        'status' => 'enabled',
        'status_reason' => null,
        'last_synced_at' => now(),
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'project-a',
    ]);
    $otherProject = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'project-b',
    ]);

    // Pre-create a request for a different project with a known payment_intent_id
    SongRequest::factory()->active()->create([
        'project_id' => $otherProject->id,
        'payment_intent_id' => 'pi_cross_project',
    ]);

    $response = $this->get('/request/confirmation?redirect_status=succeeded&submission=request&project_slug='
        .$project->slug.'&payment_intent=pi_cross_project');

    $response->assertRedirect(route('project.page', ['projectSlug' => $project->slug]));
    $response->assertSessionMissing('request_success');
});

it('redirects to project page without flash when request status is not active', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'played-request-project',
    ]);

    SongRequest::factory()->create([
        'project_id' => $project->id,
        'payment_intent_id' => 'pi_played_request',
        'status' => RequestStatus::Played,
    ]);

    $response = $this->get('/request/confirmation?redirect_status=succeeded&submission=request&project_slug='
        .$project->slug.'&payment_intent=pi_played_request');

    $response->assertRedirect(route('project.page', ['projectSlug' => $project->slug]));
    $response->assertSessionMissing('request_success');
});

it('resolves a tip-only payment intent on confirmation redirect', function () {
    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_tip_only_redirect',
        'country' => 'US',
        'default_currency' => 'usd',
        'details_submitted' => true,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
        'requirements_disabled_reason' => null,
        'status' => 'enabled',
        'status_reason' => null,
        'last_synced_at' => now(),
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'tip-only-confirmation-project',
    ]);

    $tipJarSong = Song::tipJarSupportSong();
    $projectId = $project->id;
    $songId = $tipJarSong->id;

    app()->instance(PaymentService::class, new class($projectId, $songId) extends PaymentService
    {
        public function __construct(
            private int $projectId,
            private int $songId,
        ) {}

        public function retrievePaymentIntentWithExpand(string $paymentIntentId, ?string $stripeAccountId = null, array $expand = []): StripePaymentIntent
        {
            return StripePaymentIntent::constructFrom([
                'id' => 'pi_tip_only_redirect',
                'object' => 'payment_intent',
                'status' => 'succeeded',
                'amount' => 500,
                'amount_received' => 500,
                'metadata' => [
                    'project_id' => (string) $this->projectId,
                    'song_id' => (string) $this->songId,
                    'tip_only' => '1',
                    'requested_from_ip' => '127.0.0.1',
                    'visitor_token' => 'tip-only-visitor-token',
                ],
                'latest_charge' => [
                    'billing_details' => [
                        'name' => 'Generous Tipper',
                    ],
                ],
            ]);
        }
    });

    $response = $this->get('/request/confirmation?redirect_status=succeeded&submission=tip&project_slug='
        .$project->slug.'&payment_intent=pi_tip_only_redirect');

    $response->assertRedirect(route('project.page', ['projectSlug' => $project->slug]));
    $response->assertSessionHas('request_success');

    $songRequest = SongRequest::query()
        ->where('payment_intent_id', 'pi_tip_only_redirect')
        ->with('audienceProfile')
        ->first();

    expect($songRequest)->not->toBeNull();
    expect($songRequest->project_id)->toBe($project->id);
    expect($songRequest->song_id)->toBe($tipJarSong->id);
    expect($songRequest->tip_amount_cents)->toBe(500);
    expect($songRequest->status)->toBe(RequestStatus::Active);
    expect($songRequest->audienceProfile->display_name)->toBe('Generous Tipper');
});
