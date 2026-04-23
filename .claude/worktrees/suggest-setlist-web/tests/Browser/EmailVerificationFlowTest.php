<?php

declare(strict_types=1);

use App\Models\User;

describe('Email verification browser flows', function () {
    it('shows the verification notice page after registering an unverified user', function () {
        $user = User::factory()->unverified()->create([
            'billing_plan' => null,
            'billing_status' => User::BILLING_STATUS_SETUP_REQUIRED,
        ]);

        $this->actingAs($user);

        $page = visit('/verify-email');

        $page->assertSee('Before getting started, could you verify your email address')
            ->assertSee('Resend Verification Email')
            ->assertSee('Log Out')
            ->assertNoJavaScriptErrors();
    });

    it('redirects unverified users from the dashboard to email verification', function () {
        $user = User::factory()->unverified()->create([
            'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
            'billing_status' => User::BILLING_STATUS_ACTIVE,
        ]);

        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertPathIs('/verify-email')
            ->assertNoJavaScriptErrors();
    });

    it('allows logging out from the verification page', function () {
        $user = User::factory()->unverified()->create([
            'billing_plan' => null,
            'billing_status' => User::BILLING_STATUS_SETUP_REQUIRED,
        ]);

        $this->actingAs($user);

        $page = visit('/verify-email');

        $page->press('Log Out')
            ->assertPathIs('/')
            ->assertNoJavaScriptErrors();
    });
});
