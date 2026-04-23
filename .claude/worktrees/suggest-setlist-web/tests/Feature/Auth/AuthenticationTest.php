<?php

declare(strict_types=1);

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

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

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('unverified users are guided to email verification after login', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->followingRedirects()->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertOk()
        ->assertSee('Before getting started, could you verify your email address');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
