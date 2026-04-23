<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200)
        ->assertSee('images/song_tipper_logo_light.png', false)
        ->assertSee('images/song_tipper_logo_dark.png', false)
        ->assertSee('favicon-light-32x32.png', false)
        ->assertSee('favicon-dark-32x32.png', false)
        ->assertSee('content="'.config('songtipper_theme.meta.light').'"', false)
        ->assertSee('content="'.config('songtipper_theme.meta.dark').'"', false)
        ->assertSee('min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse', false)
        ->assertSee('rounded-[28px] border border-ink-border-dark/70 bg-surface-muted/90', false)
        ->assertSee('inline-flex items-center justify-center gap-2 rounded-xl bg-brand px-4 py-2 text-xs font-semibold uppercase tracking-widest text-ink', false)
        ->assertDontSee('app-shell', false)
        ->assertDontSee('app-panel', false)
        ->assertDontSee('app-primary-button', false);
});

test('new users can register', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'instrument_type' => 'guitar',
        'password' => 'TestPassword123',
        'password_confirmation' => 'TestPassword123',
    ]);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->assertAuthenticated();
    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->instrument_type)->toBe('guitar');
    Notification::assertSentTo($user, VerifyEmail::class);
    $response->assertRedirect(route('verification.notice', absolute: false));
});

test('new users are registered with the free billing plan and earning status', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'instrument_type' => 'vocals',
        'password' => 'TestPassword123',
        'password_confirmation' => 'TestPassword123',
    ]);

    $user = User::query()->where('email', 'newuser@example.com')->firstOrFail();

    expect($user->billing_plan)->toBe(User::BILLING_PLAN_FREE)
        ->and($user->billing_status)->toBe(User::BILLING_STATUS_EARNING);
});

test('registration requires a primary instrument', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'noinstrument@example.com',
        'password' => 'TestPassword123',
        'password_confirmation' => 'TestPassword123',
    ]);

    $response->assertSessionHasErrors('instrument_type');
    $this->assertGuest();
});

test('new users can register with a secondary instrument', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Multi Player',
        'email' => 'multi@example.com',
        'instrument_type' => 'guitar',
        'secondary_instrument_type' => 'vocals',
        'password' => 'TestPassword123',
        'password_confirmation' => 'TestPassword123',
    ]);

    $user = User::query()->where('email', 'multi@example.com')->firstOrFail();

    expect($user->instrument_type)->toBe('guitar')
        ->and($user->secondary_instrument_type)->toBe('vocals');
});
