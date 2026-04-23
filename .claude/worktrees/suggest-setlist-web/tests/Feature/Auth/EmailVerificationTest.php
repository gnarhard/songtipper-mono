<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create([
        'billing_plan' => null,
        'billing_status' => User::BILLING_STATUS_SETUP_REQUIRED,
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

// EmailVerificationPromptController tests

test('verified user with billing is redirected to dashboard from verification prompt', function () {
    $user = billingReadyUser();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('verified user without billing plan is redirected to dashboard from verification prompt', function () {
    $user = setupRequiredUser();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertRedirect(route('dashboard', absolute: false));
});

// EmailVerificationNotificationController tests

test('verified user with billing is redirected to dashboard when requesting verification notification', function () {
    $user = billingReadyUser();

    $response = $this->actingAs($user)->post('/email/verification-notification');

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('verified user without billing plan is redirected to dashboard when requesting verification notification', function () {
    $user = setupRequiredUser();

    $response = $this->actingAs($user)->post('/email/verification-notification');

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('unverified user receives verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->post('/email/verification-notification');

    Notification::assertSentTo($user, VerifyEmail::class);
    $response->assertSessionHas('status', 'verification-link-sent');
});

// VerifyEmailController tests

test('already verified user with billing is redirected to dashboard on verify', function () {
    $user = billingReadyUser();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('verified user with billing completing verification redirects to dashboard', function () {
    $user = User::factory()->unverified()->create([
        'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        'billing_status' => User::BILLING_STATUS_ACTIVE,
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});
