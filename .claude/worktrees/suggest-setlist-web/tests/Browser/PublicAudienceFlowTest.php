<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Request as SongRequest;
use App\Models\Song;
use App\Models\User;
use App\Services\PaymentService;
use Stripe\PaymentIntent as StripePaymentIntent;

function fakeBrowserPaymentIntent(): void
{
    app()->instance(PaymentService::class, new class
    {
        public function createPaymentIntent(
            Project $project,
            string $stripeAccountId,
            int $tipAmountCents,
            array $metadata = []
        ): StripePaymentIntent {
            return StripePaymentIntent::constructFrom([
                'id' => 'pi_browser_test_'.(string) $tipAmountCents,
                'object' => 'payment_intent',
                'client_secret' => 'pi_browser_test_secret_'.(string) $tipAmountCents,
                'status' => 'requires_payment_method',
            ]);
        }
    });

    config()->set('services.stripe.key', 'pk_test_browser');
}

describe('Public audience browser flows', function () {
    it('filters repertoire on the public project page and clears filters', function () {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Filter Test Project',
            'slug' => 'filter-test-project',
            'performer_info_url' => 'https://example.com/performer',
            'is_accepting_requests' => true,
            'is_accepting_original_requests' => true,
        ]);

        $purpleRain = Song::factory()->create([
            'title' => 'Purple Rain',
            'artist' => 'Prince',
            'era' => '80s',
            'genre' => 'Rock',
        ]);
        $wonderwall = Song::factory()->create([
            'title' => 'Wonderwall',
            'artist' => 'Oasis',
            'era' => '90s',
            'genre' => 'Rock',
        ]);

        ProjectSong::factory()->create([
            'project_id' => $project->id,
            'song_id' => $purpleRain->id,
            'instrumental' => true,
        ]);
        ProjectSong::factory()->create([
            'project_id' => $project->id,
            'song_id' => $wonderwall->id,
        ]);

        $page = visit('/project/'.$project->slug);

        $page->assertSee('Filter Test Project')
            ->assertSee('Learn More About the Performer')
            ->assertSee('Tip Only')
            ->assertSee('Request an Original')
            ->assertDontSee('Quick Actions')
            ->assertSee('Higher tips move songs up the queue. Earlier requests win ties. Tips go directly to the performer.')
            ->assertDontSee('Your Profile')
            ->assertDontSee('My Achievements')
            ->assertDontSee('Who\'s Here Leaderboard')
            ->assertSee('Purple Rain (instrumental)')
            ->assertSee('Wonderwall')
            ->press('Filter Songs')
            ->fill('input[placeholder="Search Title"]', 'Purple')
            ->wait(1)
            ->assertDontSee('Tip Only')
            ->assertDontSee('Request an Original')
            ->assertSee('Purple Rain')
            ->assertDontSee('Wonderwall')
            ->press('Clear')
            ->wait(1)
            ->assertSee('Tip Only')
            ->assertSee('Request an Original')
            ->assertSee('Wonderwall')
            ->assertNoJavaScriptErrors();
    });

    it('shows requests-closed state when the project is not accepting requests', function () {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->notAcceptingRequests()
            ->create([
                'owner_user_id' => $owner->id,
                'slug' => 'closed-project',
            ]);
        $song = Song::factory()->create([
            'title' => 'No Request Song',
        ]);
        ProjectSong::factory()->create([
            'project_id' => $project->id,
            'song_id' => $song->id,
        ]);

        $page = visit('/project/'.$project->slug);

        $page->assertSee('This performer is not currently accepting requests.')
            ->assertDontSee('Tip Only')
            ->assertNoJavaScriptErrors();
    });

    it('updates request page payment label for preset and custom tips', function () {
        fakeBrowserPaymentIntent();

        $owner = User::factory()->create();
        $owner->payoutAccount()->create([
            'stripe_account_id' => 'acct_browser_request_labels',
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
            'slug' => 'request-label-project',
            'min_tip_cents' => 500,
            'quick_tip_1_cents' => 3000,
            'quick_tip_2_cents' => 1800,
            'quick_tip_3_cents' => 1200,
            'is_accepting_requests' => true,
        ]);
        $song = Song::factory()->create([
            'title' => 'Browser Request Song',
            'artist' => 'Browser Artist',
        ]);
        ProjectSong::factory()->create([
            'project_id' => $project->id,
            'song_id' => $song->id,
        ]);

        $page = visit("/project/{$project->slug}/request/{$song->id}");

        $page->assertSee('Browser Request Song')
            ->assertDontSee('Minimum tip:')
            ->assertSee('Tip $30')
            ->click('$18')
            ->wait(1)
            ->assertSee('Tip $18')
            ->click('Custom')
            ->fill('customTip', '80')
            ->wait(1)
            ->assertSee('Tip $80');
    });

    it('shows exact queue guidance using the highest open request tip', function () {
        fakeBrowserPaymentIntent();

        $owner = User::factory()->create();
        $owner->payoutAccount()->create([
            'stripe_account_id' => 'acct_browser_queue_top_copy',
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
            'slug' => 'queue-top-copy-project',
            'min_tip_cents' => 500,
            'is_accepting_requests' => true,
        ]);
        $song = Song::factory()->create([
            'title' => 'Queue Top Song',
            'artist' => 'Queue Artist',
        ]);
        ProjectSong::factory()->create([
            'project_id' => $project->id,
            'song_id' => $song->id,
        ]);

        SongRequest::factory()->active()->create([
            'project_id' => $project->id,
            'song_id' => Song::factory()->create()->id,
            'tip_amount_cents' => 5000,
            'score_cents' => 5000,
        ]);

        $page = visit("/project/{$project->slug}/request/{$song->id}");

        $page->assertSee('Add $31 more to take #1 in the queue.')
            ->click('Custom')
            ->fill('customTip', '50')
            ->wait(1)
            ->assertSee('Add $1 more to take #1 in the queue.')
            ->fill('customTip', '80')
            ->wait(1)
            ->assertSee('This tip would put your request at #1.')
            ->assertNoJavaScriptErrors();
    });

    it('shows original-request restriction when originals are disabled', function () {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $owner->id,
            'slug' => 'originals-disabled-project',
            'is_accepting_requests' => true,
            'is_accepting_original_requests' => false,
        ]);
        $originalSong = Song::originalRequestSong();

        $page = visit("/project/{$project->slug}/request/{$originalSong->id}");

        $page->assertSee('Request an Original')
            ->assertSee('This project is not currently accepting original requests.')
            ->assertDontSee('Pay $');
    });

    it('shows free-request mode when tips are disabled', function () {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $owner->id,
            'slug' => 'tips-disabled-project',
            'min_tip_cents' => 500,
            'is_accepting_requests' => true,
            'is_accepting_tips' => false,
            'is_accepting_original_requests' => true,
        ]);
        $song = Song::factory()->create([
            'title' => 'No Tips Tonight',
            'artist' => 'Venue Rules',
        ]);
        ProjectSong::factory()->create([
            'project_id' => $project->id,
            'song_id' => $song->id,
        ]);

        $projectPage = visit("/project/{$project->slug}");

        $projectPage->assertDontSee('Tip Only')
            ->assertSee('Request an Original')
            ->assertNoJavaScriptErrors();

        $requestPage = visit("/project/{$project->slug}/request/{$song->id}");

        $requestPage->assertSee('No Tips Tonight')
            ->assertDontSee('Choose Tip')
            ->assertSee('Submit Request')
            ->assertDontSee('Pay $')
            ->assertNoJavaScriptErrors();
    });

    it('shows the success confirmation state for tip submissions', function () {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $owner->id,
            'slug' => 'confirmation-success-project',
        ]);

        $page = visit("/request/confirmation?redirect_status=succeeded&submission=tip&project_slug={$project->slug}");

        $page->assertSee('Tip Sent!')
            ->assertSee('Back to Repertoire')
            ->assertSee('Request a Song Too')
            ->assertNoJavaScriptErrors();
    });

    it('returns successful song request payments to the repertoire page with a current-request section', function () {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Queue Return Project',
            'slug' => 'queue-return-project',
            'is_accepting_requests' => true,
        ]);
        $song = Song::factory()->create([
            'title' => 'Queue Return Song',
            'artist' => 'Queue Artist',
        ]);
        ProjectSong::factory()->create([
            'project_id' => $project->id,
            'song_id' => $song->id,
        ]);

        SongRequest::factory()->active()->create([
            'project_id' => $project->id,
            'song_id' => Song::factory()->create()->id,
            'tip_amount_cents' => 5000,
            'score_cents' => 5000,
            'created_at' => now()->subMinute(),
        ]);
        SongRequest::factory()->active()->create([
            'project_id' => $project->id,
            'song_id' => $song->id,
            'tip_amount_cents' => 2000,
            'score_cents' => 2000,
            'payment_intent_id' => 'pi_browser_success_redirect',
            'created_at' => now(),
        ]);

        $page = visit('/request/confirmation?redirect_status=succeeded&submission=request&project_slug='
            .$project->slug.'&payment_intent=pi_browser_success_redirect');

        $page->assertSee('Queue Return Project')
            ->assertSee("Request submitted. You're currently #2 in the queue.")
            ->assertSee('Your Requests')
            ->assertSee('Queue Return Song')
            ->assertSee('Queue position')
            ->assertSee('#2')
            ->assertDontSee('Request Submitted!')
            ->assertNoJavaScriptErrors();
    });

    it('shows the processing confirmation state for song requests', function () {
        $page = visit('/request/confirmation?redirect_status=processing&submission=request');

        $page->assertSee('Processing Payment')
            ->assertSee('Your payment is being processed.')
            ->assertNoJavaScriptErrors();
    });

    it('shows the failed confirmation state and home fallback link', function () {
        $page = visit('/request/confirmation?redirect_status=requires_payment_method');

        $page->assertSee('Payment Failed')
            ->assertSee('Return Home')
            ->assertNoJavaScriptErrors();
    });
});
