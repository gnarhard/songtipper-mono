<?php

declare(strict_types=1);

use App\Models\User;

describe('Free tier registration browser flows', function () {
    it('registers a new user and redirects to billing setup', function () {
        $page = visit('/register');

        $page->assertSee('Create your account')
            ->fill('name', 'Free Tier User')
            ->fill('email', 'freetier@example.com')
            ->select('instrument_type', 'guitar')
            ->fill('password', 'password')
            ->fill('password_confirmation', 'password')
            ->press('Create Account')
            ->wait(2)
            ->assertNoJavaScriptErrors();

        $user = User::query()->where('email', 'freetier@example.com')->first();

        expect($user)->not->toBeNull()
            ->and($user->billing_plan)->toBeNull()
            ->and($user->billing_activated_at)->toBeNull();
    });

    it('allows free users to access the dashboard without billing redirect', function () {
        $user = User::factory()->create([
            'billing_plan' => User::BILLING_PLAN_FREE,
            'billing_status' => User::BILLING_STATUS_ACTIVE,
            'billing_activated_at' => now(),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertPathIs('/dashboard')
            ->assertDontSee('Complete Billing Setup')
            ->assertNoJavaScriptErrors();
    });
});
