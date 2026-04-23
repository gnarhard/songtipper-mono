<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\Song;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\PaymentService;
use App\Services\PayoutAccountService;
use App\Services\RequestRateLimiter;
use Mockery\MockInterface;
use Stripe\PaymentIntent;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
        'slug' => 'test-project',
        'min_tip_cents' => 500,
        'is_accepting_requests' => true,
        'is_accepting_tips' => true,
    ]);
    $this->payoutAccount = UserPayoutAccount::query()->create([
        'user_id' => $this->owner->id,
        'stripe_account_id' => 'acct_test_owner_123',
        'status' => UserPayoutAccount::STATUS_ENABLED,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);
    $this->song = Song::factory()->create();
});

function mockPaymentIntent(): PaymentIntent
{
    return PaymentIntent::constructFrom([
        'id' => 'pi_test_123',
        'client_secret' => 'pi_test_123_secret_abc',
        'status' => 'requires_payment_method',
        'amount' => 1000,
        'currency' => 'usd',
    ]);
}

it('creates a payment intent and defers request creation for paid requests', function () {
    $this->mock(PaymentService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createPaymentIntent')
            ->once()
            ->withArgs(function (
                Project $project,
                string $stripeAccountId,
                int $tipAmountCents,
                array $metadata
            ): bool {
                return $project->id === $this->project->id &&
                    $stripeAccountId === 'acct_test_owner_123' &&
                    $tipAmountCents === 1000 &&
                    (int) ($metadata['song_id'] ?? 0) === $this->song->id;
            })
            ->andReturn(mockPaymentIntent());
    });

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 1000,
        'note' => 'Happy birthday!',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('request_id', null);
    $response->assertJsonPath('client_secret', 'pi_test_123_secret_abc');
    $response->assertJsonPath('payment_intent_id', 'pi_test_123');
    $response->assertJsonPath('requires_payment', true);
    $response->assertJsonPath('stripe_account_id', 'acct_test_owner_123');

    expect(SongRequest::count())->toBe(0);
});

it('rounds paid request tips up to the nearest dollar before creating payment intent', function () {
    $this->mock(PaymentService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createPaymentIntent')
            ->once()
            ->withArgs(function (
                Project $project,
                string $stripeAccountId,
                int $tipAmountCents,
                array $metadata
            ): bool {
                return $project->id === $this->project->id &&
                    $stripeAccountId === 'acct_test_owner_123' &&
                    $tipAmountCents === 1300 &&
                    (int) ($metadata['song_id'] ?? 0) === $this->song->id;
            })
            ->andReturn(PaymentIntent::constructFrom([
                'id' => 'pi_test_rounded_tip',
                'client_secret' => 'pi_test_rounded_tip_secret',
                'status' => 'requires_payment_method',
                'amount' => 1300,
                'currency' => 'usd',
            ]));
    });

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 1250,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('payment_intent_id', 'pi_test_rounded_tip')
        ->assertJsonPath('client_secret', 'pi_test_rounded_tip_secret')
        ->assertJsonPath('requires_payment', true);

    expect(SongRequest::count())->toBe(0);
});

it('allows tip-only paid requests without a song id', function () {
    $tipJarSupportSong = Song::tipJarSupportSong();

    $this->mock(PaymentService::class, function (MockInterface $mock) use ($tipJarSupportSong) {
        $mock->shouldReceive('createPaymentIntent')
            ->once()
            ->withArgs(function (
                Project $project,
                string $stripeAccountId,
                int $tipAmountCents,
                array $metadata
            ) use ($tipJarSupportSong): bool {
                return $project->id === $this->project->id &&
                    $stripeAccountId === 'acct_test_owner_123' &&
                    $tipAmountCents === 1000 &&
                    (int) ($metadata['song_id'] ?? 0) === $tipJarSupportSong->id &&
                    ($metadata['tip_only'] ?? null) === '1';
            })
            ->andReturn(mockPaymentIntent());
    });

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'tip_only' => true,
        'tip_amount_cents' => 1000,
        'note' => 'Keep it going',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('request_id', null);
    $response->assertJsonPath('client_secret', 'pi_test_123_secret_abc');
    $response->assertJsonPath('payment_intent_id', 'pi_test_123');
    $response->assertJsonPath('requires_payment', true);
    $response->assertJsonPath('stripe_account_id', 'acct_test_owner_123');

    expect(SongRequest::count())->toBe(0);
});

it('rejects requests when project is not accepting', function () {
    $this->project->update(['is_accepting_requests' => false]);

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 1000,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'This project is not currently accepting requests.');
});

it('rejects paid requests when tips are disabled', function () {
    $this->project->update(['is_accepting_tips' => false]);

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 1000,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'This project is not currently accepting tips.');
});

it('rejects tip-only requests when tips are disabled', function () {
    $this->project->update(['is_accepting_tips' => false]);

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'tip_only' => true,
        'tip_amount_cents' => 0,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'This project is not currently accepting tips.');
});

it('rejects requests when payout setup is incomplete', function () {
    $this->payoutAccount->update([
        'status' => UserPayoutAccount::STATUS_PENDING,
        'charges_enabled' => false,
        'payouts_enabled' => false,
    ]);
    $pendingAccount = $this->payoutAccount->fresh();

    $this->mock(PayoutAccountService::class, function (MockInterface $mock) use ($pendingAccount): void {
        $mock->shouldReceive('refreshAccount')
            ->once()
            ->withArgs(
                fn (UserPayoutAccount $account): bool => $account->id === $pendingAccount->id
            )
            ->andReturn($pendingAccount);
    });

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 1000,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'payout_setup_incomplete')
        ->assertJsonPath('message', 'This project is not currently accepting requests.');
});

it('creates payment intent when stripe refresh resolves stale payout status', function () {
    $this->payoutAccount->update([
        'status' => UserPayoutAccount::STATUS_PENDING,
        'status_reason' => 'capabilities_pending',
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'details_submitted' => true,
    ]);

    $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshAccount')
            ->once()
            ->andReturnUsing(function (UserPayoutAccount $account): UserPayoutAccount {
                $account->update([
                    'status' => UserPayoutAccount::STATUS_ENABLED,
                    'status_reason' => null,
                    'charges_enabled' => true,
                    'payouts_enabled' => true,
                    'requirements_currently_due' => [],
                    'requirements_past_due' => [],
                ]);

                return $account->fresh();
            });
    });

    $this->mock(PaymentService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('createPaymentIntent')
            ->once()
            ->withArgs(function (
                Project $project,
                string $stripeAccountId,
                int $tipAmountCents,
                array $metadata
            ): bool {
                return $project->id === $this->project->id &&
                    $stripeAccountId === 'acct_test_owner_123' &&
                    $tipAmountCents === 1000 &&
                    (int) ($metadata['song_id'] ?? 0) === $this->song->id;
            })
            ->andReturn(mockPaymentIntent());
    });

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 1000,
        'note' => 'Fresh after Stripe confirmation',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('requires_payment', true)
        ->assertJsonPath('payment_intent_id', 'pi_test_123')
        ->assertJsonPath('stripe_account_id', 'acct_test_owner_123');

    $this->assertDatabaseHas('user_payout_accounts', [
        'id' => $this->payoutAccount->id,
        'status' => UserPayoutAccount::STATUS_ENABLED,
        'charges_enabled' => true,
        'payouts_enabled' => true,
    ]);
});

it('rejects requests below minimum tip', function () {
    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 200,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('min_tip_cents', 500);
});

it('creates a free request when tips are disabled even if min tip and payout setup would block paid requests', function () {
    $this->project->update([
        'is_accepting_tips' => false,
        'min_tip_cents' => 500,
    ]);
    $this->payoutAccount->update([
        'status' => UserPayoutAccount::STATUS_PENDING,
        'charges_enabled' => false,
        'payouts_enabled' => false,
    ]);

    $this->mock(PaymentService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('createPaymentIntent');
    });
    $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('refreshAccount');
    });

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 0,
        'note' => 'Free request only',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('client_secret', null)
        ->assertJsonPath('payment_intent_id', null)
        ->assertJsonPath('requires_payment', false);

    $requestId = $response->json('request_id');

    expect($requestId)->not->toBeNull();

    $songRequest = SongRequest::query()->find($requestId);
    expect($songRequest)->not->toBeNull();
    expect($songRequest->song_id)->toBe($this->song->id);
    expect($songRequest->tip_amount_cents)->toBe(0);
    expect($songRequest->note)->toBe('Free request only');
    expect($songRequest->payment_provider)->toBe('none');
});

it('validates required fields', function () {
    $response = $this->postJson('/api/v1/public/projects/test-project/requests', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['song_id', 'tip_amount_cents']);
});

it('validates song exists', function () {
    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => 99999,
        'tip_amount_cents' => 1000,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['song_id']);
});

it('validates maximum lengths for optional fields', function () {
    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 1000,
        'note' => str_repeat('a', 501),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['note']);
});

it('returns 404 for non-existent project', function () {
    $response = $this->postJson('/api/v1/public/projects/non-existent/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 1000,
    ]);

    $response->assertNotFound();
});

it('creates a request immediately for zero-dollar requests when allowed', function () {
    $this->project->update(['min_tip_cents' => 0]);

    $this->mock(PaymentService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('createPaymentIntent');
    });

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 0,
        'note' => 'No tip request',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('client_secret', null)
        ->assertJsonPath('payment_intent_id', null)
        ->assertJsonPath('requires_payment', false);

    $requestId = $response->json('request_id');

    expect($requestId)->not->toBeNull();

    $songRequest = SongRequest::query()->find($requestId);
    expect($songRequest)->not->toBeNull();
    expect($songRequest->song_id)->toBe($this->song->id);
    expect($songRequest->tip_amount_cents)->toBe(0);
    expect($songRequest->note)->toBe('No tip request');
    expect($songRequest->payment_provider)->toBe('none');
});

it('stores optional note as null for zero-dollar requests when not provided', function () {
    $this->project->update(['min_tip_cents' => 0]);

    $this->mock(PaymentService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('createPaymentIntent');
    });

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 0,
    ]);

    $response->assertSuccessful();

    $requestId = $response->json('request_id');
    expect(SongRequest::query()->find($requestId)?->note)->toBeNull();
});

it('stores tip-only zero-dollar submissions as already played', function () {
    $this->project->update(['min_tip_cents' => 0]);

    $this->mock(PaymentService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('createPaymentIntent');
    });

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'tip_only' => true,
        'tip_amount_cents' => 0,
        'note' => 'No request, just support',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('client_secret', null)
        ->assertJsonPath('payment_intent_id', null)
        ->assertJsonPath('requires_payment', false);

    $requestId = $response->json('request_id');
    $songRequest = SongRequest::query()->findOrFail($requestId);

    expect($songRequest->song_id)->toBe(Song::tipJarSupportSong()->id);
    expect($songRequest->status->value)->toBe('active');
    expect($songRequest->played_at)->toBeNull();
});

it('rejects original requests when original requests are disabled', function () {
    $this->project->update(['is_accepting_original_requests' => false]);
    $originalSong = Song::originalRequestSong();

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $originalSong->id,
        'tip_amount_cents' => 1000,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'This project is not currently accepting original requests.');
});

it('returns 429 when rate limited for the same song', function () {
    $this->mock(RequestRateLimiter::class, function (MockInterface $mock) {
        $mock->shouldReceive('canRequest')->once()->andReturn(false);
    });

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 1000,
    ]);

    $response->assertStatus(429)
        ->assertJsonPath('code', 'request_rate_limited')
        ->assertJsonPath('remaining_attempts', 0);
});

it('reports and continues when payout account refresh throws an exception', function () {
    $this->payoutAccount->update([
        'status' => UserPayoutAccount::STATUS_PENDING,
        'charges_enabled' => false,
        'payouts_enabled' => false,
    ]);

    $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshAccount')
            ->once()
            ->andThrow(new RuntimeException('Stripe API error'));
    });

    $response = $this->postJson('/api/v1/public/projects/test-project/requests', [
        'song_id' => $this->song->id,
        'tip_amount_cents' => 1000,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'payout_setup_incomplete');
});
