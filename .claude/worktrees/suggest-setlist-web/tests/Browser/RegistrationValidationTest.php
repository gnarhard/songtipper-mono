<?php

declare(strict_types=1);

describe('Registration validation browser flows', function () {
    it('rejects duplicate email addresses', function () {
        billingReadyUser([
            'email' => 'taken@example.com',
        ]);

        $page = visit('/register');

        $page->fill('name', 'New Performer')
            ->fill('email', 'taken@example.com')
            ->select('instrument_type', 'vocals')
            ->fill('password', 'password123')
            ->fill('password_confirmation', 'password123')
            ->press('Create Account')
            ->assertPathIs('/register')
            ->assertSee('email has already been taken')
            ->assertNoJavaScriptErrors();
    });

    it('shows the login link on the registration page', function () {
        $page = visit('/register');

        $page->assertSee('Already registered?')
            ->assertNoJavaScriptErrors();
    });

    it('shows the registration link on the login page', function () {
        $page = visit('/login');

        $page->assertSee('Sign in to your performer dashboard')
            ->assertNoJavaScriptErrors();
    });
});
