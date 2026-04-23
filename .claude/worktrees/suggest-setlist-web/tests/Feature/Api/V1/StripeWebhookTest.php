<?php

declare(strict_types=1);

use App\Enums\RequestStatus;
use App\Models\AudienceProfile;
use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\Song;
use App\Models\User;
use App\Models\UserPayoutAccount;

beforeEach(function () {
    config(['cashier.webhook.secret' => 'whsec_test_secret']);
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $this->song = Song::factory()->create();
});

function generateStripeSignature(string $payload): string
{
    $timestamp = time();
    $secret = config('cashier.webhook.secret');
    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    return "t={$timestamp},v1={$signature}";
}

it('verifies stripe signature', function () {
    $response = $this->postJson('/stripe/webhook', [], [
        'Stripe-Signature' => 'invalid_signature',
    ]);

    $response->assertStatus(403);
});

it('creates a request on payment_intent.succeeded', function () {
    $payload = json_encode([
        'id' => 'evt_test_123',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_test_success',
                'amount' => 1000,
                'amount_received' => 1000,
                'metadata' => [
                    'project_id' => (string) $this->project->id,
                    'song_id' => (string) $this->song->id,
                    'note' => 'Play this next',
                    'requested_from_ip' => '127.0.0.1',
                ],
                'latest_charge' => [
                    'balance_transaction' => [
                        'fee' => 59,
                        'net' => 941,
                    ],
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    );

    $response->assertSuccessful();

    $songRequest = SongRequest::query()
        ->where('payment_intent_id', 'pi_test_success')
        ->first();

    expect($songRequest)->not->toBeNull();
    expect($songRequest->project_id)->toBe($this->project->id);
    expect($songRequest->song_id)->toBe($this->song->id);
    expect($songRequest->tip_amount_cents)->toBe(1000);
    expect($songRequest->note)->toBe('Play this next');
    expect($songRequest->status)->toBe(RequestStatus::Active);
    expect($songRequest->stripe_fee_amount_cents)->toBe(59);
    expect($songRequest->stripe_net_amount_cents)->toBe(941);
});

it('updates audience profile display_name from billing_details.name on payment_intent.succeeded', function () {
    $payload = json_encode([
        'id' => 'evt_billing_name_test',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_billing_name_test',
                'amount' => 1500,
                'amount_received' => 1500,
                'metadata' => [
                    'project_id' => (string) $this->project->id,
                    'song_id' => (string) $this->song->id,
                    'visitor_token' => 'test-visitor-token-billing-name',
                    'requested_from_ip' => '127.0.0.1',
                ],
                'latest_charge' => [
                    'billing_details' => [
                        'name' => 'Jane Smith',
                    ],
                    'balance_transaction' => [
                        'fee' => 74,
                        'net' => 1426,
                    ],
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    );

    $response->assertSuccessful();

    $songRequest = SongRequest::query()
        ->where('payment_intent_id', 'pi_billing_name_test')
        ->first();

    expect($songRequest)->not->toBeNull();

    $audienceProfile = AudienceProfile::query()
        ->where('project_id', $this->project->id)
        ->where('visitor_token', 'test-visitor-token-billing-name')
        ->first();

    expect($audienceProfile)->not->toBeNull();
    expect($audienceProfile->display_name)->toBe('Jane Smith');
});

it('keeps audience profile display_name when billing_details.name is absent', function () {
    $payload = json_encode([
        'id' => 'evt_no_billing_name_test',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_no_billing_name_test',
                'amount' => 800,
                'amount_received' => 800,
                'metadata' => [
                    'project_id' => (string) $this->project->id,
                    'song_id' => (string) $this->song->id,
                    'visitor_token' => 'test-visitor-token-no-billing-name',
                    'requested_from_ip' => '127.0.0.1',
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    );

    $response->assertSuccessful();

    $audienceProfile = AudienceProfile::query()
        ->where('project_id', $this->project->id)
        ->where('visitor_token', 'test-visitor-token-no-billing-name')
        ->first();

    expect($audienceProfile)->not->toBeNull();
    // display_name should be null — only real names from Stripe billing details are saved
    expect($audienceProfile->display_name)->toBeNull();
});

it('stores stripe settlement data from charge.updated events', function () {
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
        'payment_provider' => 'stripe',
        'payment_intent_id' => 'pi_settlement_sync',
        'stripe_fee_amount_cents' => null,
        'stripe_net_amount_cents' => null,
    ]);

    $payload = json_encode([
        'id' => 'evt_charge_updated_123',
        'type' => 'charge.updated',
        'data' => [
            'object' => [
                'id' => 'ch_settlement_sync',
                'payment_intent' => 'pi_settlement_sync',
                'balance_transaction' => [
                    'fee' => 144,
                    'net' => 1856,
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    );

    $response->assertSuccessful();

    $request = SongRequest::query()
        ->where('payment_intent_id', 'pi_settlement_sync')
        ->firstOrFail();

    expect($request->stripe_fee_amount_cents)->toBe(144);
    expect($request->stripe_net_amount_cents)->toBe(1856);
});

it('creates a played request for tip-only payment_intent.succeeded events', function () {
    $tipJarSupportSong = Song::tipJarSupportSong();
    $payload = json_encode([
        'id' => 'evt_tip_only_123',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_tip_only_success',
                'amount' => 2400,
                'amount_received' => 2400,
                'metadata' => [
                    'project_id' => (string) $this->project->id,
                    'tip_only' => '1',
                    'note' => 'No request tonight',
                    'requested_from_ip' => '127.0.0.1',
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    );

    $response->assertSuccessful();

    $songRequest = SongRequest::query()
        ->where('payment_intent_id', 'pi_tip_only_success')
        ->first();

    expect($songRequest)->not->toBeNull();
    expect($songRequest->song_id)->toBe($tipJarSupportSong->id);
    expect($songRequest->status)->toBe(RequestStatus::Active);
    expect($songRequest->played_at)->toBeNull();
});

it('is idempotent for duplicate payment_intent.succeeded webhooks', function () {
    SongRequest::factory()->active()->create([
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
        'payment_intent_id' => 'pi_test_existing',
        'status' => RequestStatus::Active,
    ]);

    $payload = json_encode([
        'id' => 'evt_test_789',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_test_existing',
                'amount' => 1000,
                'amount_received' => 1000,
                'metadata' => [
                    'project_id' => (string) $this->project->id,
                    'song_id' => (string) $this->song->id,
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    );

    $response->assertSuccessful();

    expect(SongRequest::query()
        ->where('payment_intent_id', 'pi_test_existing')
        ->count())->toBe(1);
});

it('ignores payment_intent.succeeded events with missing metadata', function () {
    $payload = json_encode([
        'id' => 'evt_test_missing_metadata',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_missing_metadata',
                'amount' => 1000,
                'amount_received' => 1000,
                'metadata' => [],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    );

    $response->assertSuccessful();

    expect(SongRequest::query()
        ->where('payment_intent_id', 'pi_missing_metadata')
        ->exists())->toBeFalse();
});

it('acknowledges payment_intent.payment_failed without creating a request', function () {
    $payload = json_encode([
        'id' => 'evt_test_101',
        'type' => 'payment_intent.payment_failed',
        'data' => [
            'object' => [
                'id' => 'pi_test_failed',
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    );

    $response->assertSuccessful();

    expect(SongRequest::query()
        ->where('payment_intent_id', 'pi_test_failed')
        ->exists())->toBeFalse();
});

it('handles unrecognized webhook events gracefully', function () {
    $payload = json_encode([
        'id' => 'evt_test_unknown',
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'sub_test',
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    );

    $response->assertSuccessful();
});

it('syncs payout account state from account.updated events', function () {
    $this->project->update(['is_accepting_requests' => false]);

    $payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->owner->id,
        'stripe_account_id' => 'acct_sync_test_1',
        'status' => UserPayoutAccount::STATUS_PENDING,
        'status_reason' => 'requirements_due',
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'details_submitted' => false,
        'requirements_currently_due' => ['external_account'],
        'requirements_past_due' => [],
    ]);

    $payload = json_encode([
        'id' => 'evt_acct_updated_123',
        'type' => 'account.updated',
        'data' => [
            'object' => [
                'id' => 'acct_sync_test_1',
                'details_submitted' => true,
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'requirements' => [
                    'currently_due' => [],
                    'past_due' => [],
                    'disabled_reason' => null,
                ],
                'country' => 'US',
                'default_currency' => 'usd',
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    );

    $response->assertSuccessful();

    $payoutAccount->refresh();

    expect($payoutAccount->status)->toBe(UserPayoutAccount::STATUS_ENABLED);
    expect($payoutAccount->status_reason)->toBeNull();
    expect($payoutAccount->charges_enabled)->toBeTrue();
    expect($payoutAccount->payouts_enabled)->toBeTrue();
    expect($this->project->fresh()->is_accepting_requests)->toBeTrue();
});

it('does not force-enable requests when payout status was already enabled', function () {
    $this->project->update(['is_accepting_requests' => false]);

    $payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->owner->id,
        'stripe_account_id' => 'acct_sync_already_enabled',
        'status' => UserPayoutAccount::STATUS_ENABLED,
        'status_reason' => null,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);

    $payload = json_encode([
        'id' => 'evt_acct_updated_already_enabled',
        'type' => 'account.updated',
        'data' => [
            'object' => [
                'id' => 'acct_sync_already_enabled',
                'details_submitted' => true,
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'requirements' => [
                    'currently_due' => [],
                    'past_due' => [],
                    'disabled_reason' => null,
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    )->assertSuccessful();

    $payoutAccount->refresh();

    expect($payoutAccount->status)->toBe(UserPayoutAccount::STATUS_ENABLED);
    expect($this->project->fresh()->is_accepting_requests)->toBeFalse();
});

it('does not force-enable requests for projects with tips disabled', function () {
    $this->project->update([
        'is_accepting_requests' => false,
        'is_accepting_tips' => false,
    ]);

    $payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->owner->id,
        'stripe_account_id' => 'acct_sync_tips_disabled_enabled',
        'status' => UserPayoutAccount::STATUS_PENDING,
        'status_reason' => 'requirements_due',
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'details_submitted' => false,
        'requirements_currently_due' => ['external_account'],
        'requirements_past_due' => [],
    ]);

    $payload = json_encode([
        'id' => 'evt_acct_updated_tips_disabled_enabled',
        'type' => 'account.updated',
        'data' => [
            'object' => [
                'id' => 'acct_sync_tips_disabled_enabled',
                'details_submitted' => true,
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'requirements' => [
                    'currently_due' => [],
                    'past_due' => [],
                    'disabled_reason' => null,
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    )->assertSuccessful();

    $payoutAccount->refresh();

    expect($payoutAccount->status)->toBe(UserPayoutAccount::STATUS_ENABLED);
    expect($this->project->fresh()->is_accepting_requests)->toBeFalse();
});

it('treats pending verification as setup in progress after account updates', function () {
    $payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->owner->id,
        'stripe_account_id' => 'acct_sync_pending_verification',
        'status' => UserPayoutAccount::STATUS_RESTRICTED,
        'status_reason' => 'requirements.pending_verification',
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);

    $payload = json_encode([
        'id' => 'evt_acct_updated_pending_verification',
        'type' => 'account.updated',
        'data' => [
            'object' => [
                'id' => 'acct_sync_pending_verification',
                'details_submitted' => true,
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'requirements' => [
                    'currently_due' => [],
                    'past_due' => [],
                    'disabled_reason' => 'requirements.pending_verification',
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    )->assertSuccessful();

    $payoutAccount->refresh();

    expect($payoutAccount->status)->toBe(UserPayoutAccount::STATUS_PENDING);
    expect($payoutAccount->status_reason)->toBe('capabilities_pending');
});

it('does not force-disable requests for projects with tips disabled', function () {
    $this->project->update([
        'is_accepting_requests' => true,
        'is_accepting_tips' => false,
    ]);

    $payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->owner->id,
        'stripe_account_id' => 'acct_sync_tips_disabled_restricted',
        'status' => UserPayoutAccount::STATUS_ENABLED,
        'status_reason' => null,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);

    $payload = json_encode([
        'id' => 'evt_acct_updated_tips_disabled_restricted',
        'type' => 'account.updated',
        'data' => [
            'object' => [
                'id' => 'acct_sync_tips_disabled_restricted',
                'details_submitted' => true,
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'requirements' => [
                    'currently_due' => ['external_account'],
                    'past_due' => [],
                    'disabled_reason' => 'requirements.pending_verification',
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    )->assertSuccessful();

    $payoutAccount->refresh();

    expect($payoutAccount->status)->toBe(UserPayoutAccount::STATUS_PENDING);
    expect($this->project->fresh()->is_accepting_requests)->toBeTrue();
});

it('keeps restricted status for non-verification disabled reasons after account updates', function () {
    $payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->owner->id,
        'stripe_account_id' => 'acct_sync_restricted_reason',
        'status' => UserPayoutAccount::STATUS_PENDING,
        'status_reason' => 'capabilities_pending',
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);

    $payload = json_encode([
        'id' => 'evt_acct_updated_restricted_reason',
        'type' => 'account.updated',
        'data' => [
            'object' => [
                'id' => 'acct_sync_restricted_reason',
                'details_submitted' => true,
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'requirements' => [
                    'currently_due' => [],
                    'past_due' => [],
                    'disabled_reason' => 'rejected.fraud',
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $signature],
        $payload
    )->assertSuccessful();

    $payoutAccount->refresh();

    expect($payoutAccount->status)->toBe(UserPayoutAccount::STATUS_RESTRICTED);
    expect($payoutAccount->status_reason)->toBe('rejected.fraud');
});

it('does not change free-trial billing status when request payments succeed', function () {
    $this->owner->forceFill([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_TRIALING,
    ])->save();

    $payload = json_encode([
        'id' => 'evt_trial_request_payment',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_trial_request_payment',
                'amount' => 2400,
                'amount_received' => 2400,
                'metadata' => [
                    'project_id' => (string) $this->project->id,
                    'song_id' => (string) $this->song->id,
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $payload)
        ->assertSuccessful();

    $this->owner->refresh();

    expect($this->owner->billing_status)->toBe(User::BILLING_STATUS_TRIALING);
});

it('does not change complimentary billing status when request payments succeed', function () {
    $this->owner->forceFill([
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
    ])->save();

    $payload = json_encode([
        'id' => 'evt_discount_request_payment',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_discount_request_payment',
                'amount' => 2400,
                'amount_received' => 2400,
                'metadata' => [
                    'project_id' => (string) $this->project->id,
                    'song_id' => (string) $this->song->id,
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $payload)
        ->assertSuccessful();

    $this->owner->refresh();

    expect($this->owner->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED);
    expect($this->owner->billing_discount_type)->toBe(User::BILLING_DISCOUNT_LIFETIME);
});

it('does not process duplicate request webhooks twice for billing state', function () {
    $this->owner->forceFill([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ])->save();

    $payload = json_encode([
        'id' => 'evt_duplicate_billing_state',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_duplicate_billing_state',
                'amount' => 2000,
                'amount_received' => 2000,
                'metadata' => [
                    'project_id' => (string) $this->project->id,
                    'song_id' => (string) $this->song->id,
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $payload)
        ->assertSuccessful();

    $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $payload)
        ->assertSuccessful();

    $this->owner->refresh();

    expect($this->owner->billing_status)->toBe(User::BILLING_STATUS_ACTIVE);
    expect(SongRequest::query()->where('payment_intent_id', 'pi_duplicate_billing_state')->count())->toBe(1);
});

it('marks recurring billing active on invoice paid webhook', function () {
    $this->owner->forceFill([
        'stripe_id' => 'cus_invoice_paid_test',
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_PAYMENT_FAILED,
        'billing_last_error_code' => 'invoice_failed',
        'billing_last_error_message' => 'Last charge failed.',
    ])->save();

    $payload = json_encode([
        'id' => 'evt_invoice_paid',
        'type' => 'invoice.paid',
        'data' => [
            'object' => [
                'id' => 'in_test_paid_1',
                'customer' => 'cus_invoice_paid_test',
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $payload)
        ->assertSuccessful();

    $this->owner->refresh();

    expect($this->owner->billing_status)->toBe(User::BILLING_STATUS_ACTIVE);
    expect($this->owner->billing_last_error_code)->toBeNull();
    expect($this->owner->billing_last_error_message)->toBeNull();
});

it('does not override complimentary access on invoice paid webhook', function () {
    $this->owner->forceFill([
        'stripe_id' => 'cus_invoice_discounted_test',
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_DISCOUNTED,
        'billing_discount_type' => User::BILLING_DISCOUNT_LIFETIME,
    ])->save();

    $payload = json_encode([
        'id' => 'evt_invoice_paid_discounted',
        'type' => 'invoice.paid',
        'data' => [
            'object' => [
                'id' => 'in_discounted_paid_1',
                'customer' => 'cus_invoice_discounted_test',
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $payload)
        ->assertSuccessful();

    $this->owner->refresh();

    expect($this->owner->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED);
    expect($this->owner->billing_discount_type)->toBe(User::BILLING_DISCOUNT_LIFETIME);
});

it('marks recurring billing failed on invoice payment failed webhook', function () {
    $this->owner->forceFill([
        'stripe_id' => 'cus_invoice_failed_test',
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ])->save();

    $payload = json_encode([
        'id' => 'evt_invoice_failed',
        'type' => 'invoice.payment_failed',
        'data' => [
            'object' => [
                'id' => 'in_test_failed_1',
                'customer' => 'cus_invoice_failed_test',
                'last_finalization_error' => [
                    'code' => 'card_declined',
                    'message' => 'Your card was declined.',
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $payload)
        ->assertSuccessful();

    $this->owner->refresh();

    expect($this->owner->billing_status)->toBe(User::BILLING_STATUS_PAYMENT_FAILED);
    expect($this->owner->billing_last_error_code)->toBe('card_declined');
    expect($this->owner->billing_last_error_message)->toBe('Your card was declined.');
});

it('syncs recurring billing status from subscription update webhook', function () {
    $this->owner->forceFill([
        'stripe_id' => 'cus_subscription_status_test',
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_PAYMENT_FAILED,
    ])->save();

    $trialingPayload = json_encode([
        'id' => 'evt_subscription_trialing',
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_status_test_1',
                'customer' => 'cus_subscription_status_test',
                'status' => 'trialing',
                'items' => [
                    'data' => [
                        [
                            'id' => 'si_status_test_1',
                            'price' => [
                                'id' => 'price_status_test_1',
                                'product' => 'prod_status_test_1',
                            ],
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($trialingPayload);

    $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $trialingPayload)
        ->assertSuccessful();

    expect($this->owner->fresh()->billing_status)->toBe(User::BILLING_STATUS_TRIALING);

    $activePayload = json_encode([
        'id' => 'evt_subscription_active',
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_status_test_1',
                'customer' => 'cus_subscription_status_test',
                'status' => 'active',
                'items' => [
                    'data' => [
                        [
                            'id' => 'si_status_test_1',
                            'price' => [
                                'id' => 'price_status_test_1',
                                'product' => 'prod_status_test_1',
                            ],
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($activePayload);

    $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $activePayload)
        ->assertSuccessful();

    expect($this->owner->fresh()->billing_status)->toBe(User::BILLING_STATUS_ACTIVE);

    $pastDuePayload = json_encode([
        'id' => 'evt_subscription_past_due',
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_status_test_1',
                'customer' => 'cus_subscription_status_test',
                'status' => 'past_due',
                'items' => [
                    'data' => [
                        [
                            'id' => 'si_status_test_1',
                            'price' => [
                                'id' => 'price_status_test_1',
                                'product' => 'prod_status_test_1',
                            ],
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $signature = generateStripeSignature($pastDuePayload);

    $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $pastDuePayload)
        ->assertSuccessful();

    $this->owner->refresh();

    expect($this->owner->billing_status)->toBe(User::BILLING_STATUS_PAYMENT_FAILED);
    expect($this->owner->billing_last_error_code)->toBe('subscription_past_due');
});

it('syncs recurring billing status from subscription deleted webhook', function () {
    $this->owner->forceFill([
        'stripe_id' => 'cus_subscription_deleted_test',
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ])->save();

    $payload = json_encode([
        'id' => 'evt_subscription_deleted',
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'id' => 'sub_deleted_test_1',
                'customer' => 'cus_subscription_deleted_test',
            ],
        ],
    ]);

    $signature = generateStripeSignature($payload);

    $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $payload)
        ->assertSuccessful();

    $this->owner->refresh();

    expect($this->owner->billing_status)->toBe(User::BILLING_STATUS_PAYMENT_FAILED);
    expect($this->owner->billing_last_error_code)->toBe('subscription_canceled');
});
