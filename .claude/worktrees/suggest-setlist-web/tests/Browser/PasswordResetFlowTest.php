<?php

declare(strict_types=1);

describe('Password reset browser flows', function () {
    it('renders the forgot password page with instructions', function () {
        $page = visit('/forgot-password');

        $page->assertSee('Forgot your password?')
            ->assertSee('Just let us know your email address')
            ->assertNoJavaScriptErrors();
    });

    it('sends a password reset link for a valid email', function () {
        $user = billingReadyUser([
            'email' => 'forgot-flow@example.com',
        ]);

        $page = visit('/forgot-password');

        $page->fill('email', $user->email)
            ->press('Email Password Reset Link')
            ->assertSee('we have emailed a password reset link')
            ->assertNoJavaScriptErrors();
    });

    it('shows the forgot password link on the login page', function () {
        $page = visit('/login');

        $page->assertSee('Forgot password?')
            ->assertNoJavaScriptErrors();
    });
});
