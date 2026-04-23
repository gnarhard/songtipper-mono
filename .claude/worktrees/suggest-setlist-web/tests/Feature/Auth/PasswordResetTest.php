<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('status', 'If an account with that email exists, we have emailed a password reset link.');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('forgot password request stays generic when the email is unknown', function () {
    $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => 'missing@example.com'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('status', 'If an account with that email exists, we have emailed a password reset link.');
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('reset password screen renders for an already authenticated user and logs them out', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        // Simulate the user clicking the email link while still holding a stale
        // browser session — they should land on the form, not the dashboard.
        $response = $this->actingAs($user)
            ->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);
        $this->assertGuest();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'TestPassword123',
            'password_confirmation' => 'TestPassword123',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('forgot password returns errors when broker returns unexpected status', function () {
    Password::shouldReceive('sendResetLink')
        ->once()
        ->andReturn('passwords.custom_error');

    $response = $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => 'user@example.com']);

    $response->assertRedirect('/forgot-password')
        ->assertSessionHasErrors(['email']);
});
