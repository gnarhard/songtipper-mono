<?php

declare(strict_types=1);

use App\Enums\RequestStatus;
use App\Models\AudienceProfile;
use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\Song;
use App\Models\User;
use App\Services\PaymentService;
use Livewire\Livewire;
use Stripe\PaymentIntent;

beforeEach(function () {
    $paymentService = Mockery::mock(PaymentService::class);
    $paymentService->shouldReceive('createPaymentIntent')
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_123',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_123_secret_abc',
            'status' => 'requires_payment_method',
        ]));
    $paymentService->shouldReceive('updatePaymentIntentAmount')
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_123',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_123_secret_abc',
            'status' => 'requires_payment_method',
        ]));

    app()->instance(PaymentService::class, $paymentService);
});

it('keeps preset buttons deselected while custom tip mode is active', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->assertSet('isCustomTip', false)
        ->call('setCustomTipMode')
        ->assertSet('isCustomTip', true)
        ->call('setTip', 2000, true)
        ->assertSet('isCustomTip', true);
});

it('renders project-configured quick tip buttons in order', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 0,
        'quick_tip_1_cents' => 3000,
        'quick_tip_2_cents' => 1800,
        'quick_tip_3_cents' => 1100,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])->assertSeeInOrder(['$30', '$18', '$11', 'Custom']);
});

it('restores the last custom tip amount when re-entering custom mode after a preset', function () {
    $paymentService = Mockery::mock(PaymentService::class);
    $paymentService->shouldReceive('createPaymentIntent')
        ->once()
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_restore',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_restore_secret_abc',
            'status' => 'requires_payment_method',
        ]));
    $paymentService->shouldReceive('updatePaymentIntentAmount')
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_restore',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_restore_secret_abc',
            'status' => 'requires_payment_method',
        ]));
    app()->instance(PaymentService::class, $paymentService);

    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_connected_restore',
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
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->call('setCustomTipMode')
        ->call('setTip', 500, true)
        ->assertSet('tipAmountCents', 500)
        ->assertSet('customTipCents', 500)
        ->call('setTip', 1000)
        ->assertSet('tipAmountCents', 1000)
        ->assertSet('isCustomTip', false)
        ->assertSet('customTipCents', 500)
        ->call('setCustomTipMode')
        ->assertSet('tipAmountCents', 500)
        ->assertSet('isCustomTip', true)
        ->assertSet('customTipCents', 500);
});

it('leaves the current preset amount untouched when entering custom mode with no prior custom tip', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->assertSet('customTipCents', null)
        ->call('setCustomTipMode')
        ->assertSet('tipAmountCents', 2000)
        ->assertSet('isCustomTip', true)
        ->assertSet('customTipCents', null);
});

it('reselects preset mode when a preset tip button is chosen', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->call('setCustomTipMode')
        ->assertSet('isCustomTip', true)
        ->call('setTip', 5000)
        ->assertSet('isCustomTip', false);
});

it('shows minimum-tip validation only after an invalid amount is entered', function () {
    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_connected_validation',
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
        'min_tip_cents' => 1500,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->assertDontSee('Tips must be at least $15.')
        ->assertSet('tipAmountError', null)
        ->assertSet('clientSecret', 'pi_test_123_secret_abc')
        ->call('setCustomTipMode')
        ->call('setTip', 1000, true)
        ->assertSet('tipAmountError', 'Tips must be at least $15.')
        ->assertSet('clientSecret', null)
        ->call('setTip', 2000, true)
        ->assertSet('tipAmountError', null)
        ->assertSet('clientSecret', 'pi_test_123_secret_abc');
});

it('rounds sub-dollar custom tips up before updating payment', function () {
    $paymentService = Mockery::mock(PaymentService::class);
    $paymentService->shouldReceive('createPaymentIntent')
        ->once()
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_stripe_minimum',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_stripe_minimum_secret_xyz',
            'status' => 'requires_payment_method',
        ]));
    $paymentService->shouldReceive('updatePaymentIntentAmount')
        ->once()
        ->withArgs(fn (string $piId, string $accountId, int $amount): bool => $piId === 'pi_test_stripe_minimum' && $amount === 100)
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_stripe_minimum',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_stripe_minimum_secret_xyz',
            'status' => 'requires_payment_method',
        ]));
    app()->instance(PaymentService::class, $paymentService);

    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_connected_stripe_minimum',
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
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->assertSet('clientSecret', 'pi_test_stripe_minimum_secret_xyz')
        ->call('setCustomTipMode')
        ->call('setTip', 49, true)
        ->assertSet('tipAmountCents', 100)
        ->assertSet('tipAmountError', null)
        ->assertSet('clientSecret', 'pi_test_stripe_minimum_secret_xyz')
        ->assertSet('error', null);
});

it('updates existing payment intent amount when switching between preset tips', function () {
    $paymentService = Mockery::mock(PaymentService::class);
    $paymentService->shouldReceive('createPaymentIntent')
        ->once()
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_switch',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_switch_secret_xyz',
            'status' => 'requires_payment_method',
        ]));
    $paymentService->shouldReceive('updatePaymentIntentAmount')
        ->twice()
        ->withArgs(fn (string $piId, string $accountId, int $amount): bool => $piId === 'pi_test_switch' && $accountId === 'acct_connected_switch')
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_switch',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_switch_secret_xyz',
            'status' => 'requires_payment_method',
        ]));
    app()->instance(PaymentService::class, $paymentService);

    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_connected_switch',
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
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->assertSet('clientSecret', 'pi_test_switch_secret_xyz')
        ->assertSet('tipAmountCents', 2000)
        ->call('setTip', 1500)
        ->assertSet('tipAmountCents', 1500)
        ->assertSet('clientSecret', 'pi_test_switch_secret_xyz')
        ->call('setTip', 1000)
        ->assertSet('tipAmountCents', 1000)
        ->assertSet('clientSecret', 'pi_test_switch_secret_xyz');
});

it('starts in custom tip mode when minimum tip exceeds every quick tip button', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 2500,
        'quick_tip_1_cents' => 2000,
        'quick_tip_2_cents' => 1500,
        'quick_tip_3_cents' => 1000,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->assertSet('tipAmountCents', 2500)
        ->assertSet('isCustomTip', true)
        ->assertDontSee('$20')
        ->assertDontSee('$15')
        ->assertDontSee('$10');
});

it('shows a validation error when the note is longer than Stripe metadata allows', function () {
    $paymentService = Mockery::mock(PaymentService::class);
    $paymentService->shouldReceive('createPaymentIntent')
        ->once()
        ->andReturn(
            PaymentIntent::constructFrom([
                'id' => 'pi_test_long_note',
                'object' => 'payment_intent',
                'client_secret' => 'pi_test_long_note_secret',
                'status' => 'requires_payment_method',
            ]),
        );
    app()->instance(PaymentService::class, $paymentService);

    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_connected_long_note',
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
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->set('note', str_repeat('a', 501))
        ->call('createPaymentIntent')
        ->assertHasErrors(['note' => ['max']])
        ->assertSee('Keep your message under 500 characters.');
});

it('does not show queue-priority tip text on the project page', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => true,
    ]);

    Livewire::test('project-page', [
        'projectSlug' => $project->slug,
    ])
        ->assertDontSee('Add $1 more to take #1 in the queue.')
        ->assertDontSee('This tip would put your request at #1.');
});

it('shows exact queue-position guidance for below-top, equal-top, and above-top tips', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    SongRequest::factory()->create([
        'project_id' => $project->id,
        'song_id' => Song::factory()->create()->id,
        'tip_amount_cents' => 5000,
        'score_cents' => 5000,
        'status' => RequestStatus::Active,
    ]);

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->assertSee('Add $31 more to take #1 in the queue.')
        ->call('setTip', 5000)
        ->assertSee('Add $1 more to take #1 in the queue.')
        ->call('setTip', 5001)
        ->assertSee('This tip would put your request at #1.');
});

it('hides queue-position guidance when the visitor already holds the highest active tip', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'visitor_token' => 'aud-top-holder',
    ]);

    SongRequest::factory()->active()->create([
        'project_id' => $project->id,
        'audience_profile_id' => $profile->id,
        'song_id' => Song::factory()->create()->id,
        'tip_amount_cents' => 5000,
        'score_cents' => 5000,
        'created_at' => now()->subMinute(),
    ]);

    Livewire::withCookies([
        'songtipper_audience_token' => 'aud-top-holder',
    ])->test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->assertDontSee('Add $31 more to take #1 in the queue.')
        ->assertDontSee('Add $1 more to take #1 in the queue.')
        ->assertDontSee('This tip would put your request at #1.');
});

it('never shows queue-priority tip text for tip-only submissions', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);

    SongRequest::factory()->create([
        'project_id' => $project->id,
        'song_id' => Song::factory()->create()->id,
        'tip_amount_cents' => 5000,
        'score_cents' => 5000,
        'status' => RequestStatus::Active,
    ]);

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => Song::tipJarSupportSong()->id,
    ])
        ->assertDontSee('Add $1 more to take #1 in the queue.')
        ->assertDontSee('This tip would put your request at #1.');
});

it('shows free request mode when tips are disabled', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 2000,
        'is_accepting_requests' => true,
        'is_accepting_tips' => false,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->assertSet('tipAmountCents', 0)
        ->assertDontSee('Choose Tip')
        ->assertSee('Tips are turned off for this event. You can still submit a request for free.')
        ->assertSee('Submit Request')
        ->assertDontSee('Pay $');
});

it('shows an error for tip-only pages when tips are disabled', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => true,
        'is_accepting_tips' => false,
    ]);

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => Song::tipJarSupportSong()->id,
    ])
        ->assertSet('error', 'This project is not currently accepting tips.')
        ->assertDontSee('Pay $');
});

it('shows a request availability error when payout account is missing', function () {
    $paymentService = Mockery::mock(PaymentService::class);
    $paymentService->shouldNotReceive('createPaymentIntent');
    app()->instance(PaymentService::class, $paymentService);

    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])->assertSet('error', 'This project is not currently accepting requests.');
});

it('passes the connected stripe account id when creating request page payment intents', function () {
    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_connected_test',
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
        'min_tip_cents' => 2000,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    $paymentService = Mockery::mock(PaymentService::class);
    $paymentService->shouldReceive('createPaymentIntent')
        ->once()
        ->withArgs(function (
            Project $capturedProject,
            string $stripeAccountId,
            int $tipAmountCents,
            array $metadata
        ) use ($project, $song): bool {
            return $capturedProject->is($project)
                && $stripeAccountId === 'acct_connected_test'
                && $tipAmountCents === 2000
                && ($metadata['song_id'] ?? null) === $song->id;
        })
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_connected',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_connected_secret',
            'status' => 'requires_payment_method',
        ]));
    app()->instance(PaymentService::class, $paymentService);

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])->assertSet('clientSecret', 'pi_test_connected_secret');
});

it('redirects free song requests back to the repertoire page with queue-position flash data', function () {
    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_free_request_redirect',
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
        'slug' => 'free-request-redirect-project',
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    SongRequest::factory()->active()->create([
        'project_id' => $project->id,
        'song_id' => Song::factory()->create()->id,
        'tip_amount_cents' => 5000,
        'score_cents' => 5000,
        'created_at' => now()->subMinute(),
    ]);

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->call('setTip', 0, true)
        ->call('createPaymentIntent')
        ->assertRedirect(route('project.page', ['projectSlug' => $project->slug]));

    $songRequest = SongRequest::query()
        ->where('project_id', $project->id)
        ->where('song_id', $song->id)
        ->where('payment_provider', 'none')
        ->latest('id')
        ->first();

    expect($songRequest)->not->toBeNull();
    expect(session('request_success.message'))->toBe("Request submitted. You're currently #2 in the queue.");
    expect(session('request_success.queue_position'))->toBe(2);
    expect(session('request_success.request_id'))->toBe($songRequest->id);
});

it('syncs the note to the payment intent metadata before payment confirmation', function () {
    $paymentService = Mockery::mock(PaymentService::class);
    $paymentService->shouldReceive('createPaymentIntent')
        ->once()
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_note_sync',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_note_sync_secret_abc',
            'status' => 'requires_payment_method',
        ]));
    $paymentService->shouldReceive('updatePaymentIntentMetadata')
        ->once()
        ->withArgs(function (string $piId, string $accountId, array $metadata): bool {
            return $piId === 'pi_test_note_sync'
                && $accountId === 'acct_connected_note_sync'
                && ($metadata['note'] ?? null) === 'Play something funky!';
        })
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_note_sync',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_note_sync_secret_abc',
            'status' => 'requires_payment_method',
        ]));
    app()->instance(PaymentService::class, $paymentService);

    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_connected_note_sync',
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
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->assertSet('clientSecret', 'pi_test_note_sync_secret_abc')
        ->set('note', 'Play something funky!')
        ->call('syncNoteToPaymentIntent');
});

it('does not call stripe when syncing note without a client secret', function () {
    $paymentService = Mockery::mock(PaymentService::class);
    $paymentService->shouldNotReceive('updatePaymentIntentMetadata');
    $paymentService->shouldNotReceive('createPaymentIntent');
    app()->instance(PaymentService::class, $paymentService);

    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'min_tip_cents' => 0,
        'is_accepting_requests' => true,
        'is_accepting_tips' => false,
    ]);
    $song = Song::factory()->create();

    Livewire::test('request-page', [
        'projectSlug' => $project->slug,
        'songId' => $song->id,
    ])
        ->assertSet('clientSecret', null)
        ->set('note', 'This should not trigger a Stripe call')
        ->call('syncNoteToPaymentIntent');
});

it('configures the request page payment element for the connected stripe account', function () {
    config()->set('services.stripe.key', 'pk_test_request_page');

    $owner = User::factory()->create();
    $owner->payoutAccount()->create([
        'stripe_account_id' => 'acct_connected_frontend',
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
        'slug' => 'connected-request-page-project',
        'min_tip_cents' => 2000,
        'is_accepting_requests' => true,
    ]);
    $song = Song::factory()->create();

    $paymentService = Mockery::mock(PaymentService::class);
    $paymentService->shouldReceive('createPaymentIntent')
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_connected_frontend',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_connected_frontend_secret',
            'status' => 'requires_payment_method',
        ]));
    app()->instance(PaymentService::class, $paymentService);

    $response = $this->get("/project/{$project->slug}/request/{$song->id}");
    $componentView = file_get_contents(resource_path('views/components/⚡request-page.blade.php'));

    $response->assertSuccessful()
        ->assertSee('data-stripe-account="acct_connected_frontend"', false);

    expect($componentView)
        ->toContain("const stripeAccountId = paymentForm.dataset.stripeAccount || '';")
        ->toContain('Stripe(paymentForm.dataset.stripeKey, {')
        ->toContain('stripeAccount: stripeAccountId')
        ->toContain("paymentMethodOrder: ['apple_pay', 'google_pay', 'card', 'cashapp', 'us_bank_account']")
        ->toContain('visibleAccordionItemsCount: 5')
        ->toContain("theme: isDark ? 'night' : 'stripe'");

    expect($componentView)
        ->not->toContain('text-[#302938]')
        ->not->toContain('dark:text-[#EDF3F1]')
        ->not->toContain('background-color: #2D2633;')
        ->not->toContain('background-color: #DCECF4;');
});
