<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

describe('Auth and billing browser flows', function () {
    it('registers a performer and routes to email verification', function () {
        $email = 'browser-'.Str::lower(Str::random(10)).'@example.com';

        $page = visit('/register');

        $page->assertSee('Create your performer account')
            ->fill('name', 'Browser Performer')
            ->fill('email', $email)
            ->fill('password', 'password')
            ->fill('password_confirmation', 'password')
            ->press('Create Account')
            ->assertPathIs('/verify-email')
            ->assertSee('Before getting started, could you verify your email address')
            ->assertNoJavaScriptErrors();

        expect(
            User::query()->where('email', $email)->exists()
        )->toBeTrue();
    });

    it('logs billing-ready performers into the dashboard', function () {
        $user = billingReadyUser([
            'email' => 'billing-ready@example.com',
        ]);
        Project::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'Browser Login Project',
        ]);

        $page = visit('/login');

        $page->assertSee('Sign in to your performer dashboard')
            ->fill('email', $user->email)
            ->fill('password', 'password')
            ->press('Sign In')
            ->assertPathIs('/dashboard')
            ->assertSee('Payout Setup and Wallet')
            ->assertSee('Browser Login Project')
            ->assertNoJavaScriptErrors();
    });

    it('redirects setup-required performers to billing setup after login', function () {
        $user = setupRequiredUser([
            'email' => 'setup-required@example.com',
        ]);

        $page = visit('/login');

        $page->fill('email', $user->email)
            ->fill('password', 'password')
            ->press('Sign In')
            ->assertPathIs('/setup/billing')
            ->assertSee('Complete Billing Setup')
            ->assertNoJavaScriptErrors();
    });

    it('shows an authentication error when credentials are invalid', function () {
        $user = billingReadyUser([
            'email' => 'invalid-auth@example.com',
        ]);

        $page = visit('/login');

        $page->fill('email', $user->email)
            ->fill('password', 'definitely-wrong-password')
            ->press('Sign In')
            ->assertPathIs('/login')
            ->assertSee('These credentials do not match our records.')
            ->assertNoJavaScriptErrors();
    });
});
