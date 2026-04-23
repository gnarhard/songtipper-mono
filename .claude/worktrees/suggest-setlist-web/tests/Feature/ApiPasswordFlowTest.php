<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('forgot password endpoint returns generic success and sends reset links', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'alice@example.com',
    ]);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => $user->email,
    ])
        ->assertOk()
        ->assertJson([
            'message' => 'If an account with that email exists, we have emailed a password reset link.',
        ]);

    Notification::assertSentTo($user, ResetPassword::class);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'missing@example.com',
    ])
        ->assertOk()
        ->assertJson([
            'message' => 'If an account with that email exists, we have emailed a password reset link.',
        ]);
});

test('password can be reset through the api', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => $user->email,
    ])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertOk()
            ->assertJson([
                'message' => 'Password has been reset.',
            ]);

        return true;
    });

    expect(Hash::check('NewPassword123!', (string) $user->refresh()->password))->toBeTrue();
});

test('authenticated user can update password through the api', function () {
    $user = User::factory()->create([
        'password' => Hash::make('CurrentPassword123!'),
    ]);
    $token = $user->createToken('mobile-app');

    $this->withToken($token->plainTextToken)
        ->putJson('/api/v1/auth/password', [
            'current_password' => 'CurrentPassword123!',
            'password' => 'UpdatedPassword123!',
            'password_confirmation' => 'UpdatedPassword123!',
        ])
        ->assertOk()
        ->assertJson([
            'message' => 'Password updated successfully.',
        ]);

    expect(Hash::check('UpdatedPassword123!', (string) $user->refresh()->password))->toBeTrue();
});

test('password update endpoint validates the current password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('CurrentPassword123!'),
    ]);
    $token = $user->createToken('mobile-app');

    $this->withToken($token->plainTextToken)
        ->putJson('/api/v1/auth/password', [
            'current_password' => 'WrongPassword123!',
            'password' => 'UpdatedPassword123!',
            'password_confirmation' => 'UpdatedPassword123!',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'current_password',
        ]);
});

test('forgot password returns 500 when password broker returns unexpected status', function () {
    Password::shouldReceive('sendResetLink')
        ->once()
        ->andReturn('passwords.unexpected_error');

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'test@example.com',
    ])
        ->assertStatus(500)
        ->assertJson([
            'message' => 'Unable to process password reset request right now.',
        ]);
});

test('reset password returns 422 when token is invalid', function () {
    $user = User::factory()->create([
        'email' => 'invalid-token@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid-token-value',
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])
        ->assertStatus(422);
});

test('password update requires authentication', function () {
    $this->putJson('/api/v1/auth/password', [
        'current_password' => 'SomePassword123!',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])
        ->assertUnauthorized();
});
