<?php

declare(strict_types=1);

describe('Billing setup browser flows', function () {
    it('shows the billing setup page with plan selection for setup-required users', function () {
        $user = setupRequiredUser();

        $this->actingAs($user);

        $page = visit('/setup/billing');

        $page->assertSee('Complete Billing Setup')
            ->assertNoJavaScriptErrors();
    });

    it('redirects billing-ready users away from the setup page to the dashboard', function () {
        $user = billingReadyUser();

        $this->actingAs($user);

        $page = visit('/setup/billing');

        $page->assertPathIs('/dashboard')
            ->assertNoJavaScriptErrors();
    });

    it('redirects setup-required users from the dashboard to billing setup', function () {
        $user = setupRequiredUser();

        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertPathIs('/setup/billing')
            ->assertSee('Complete Billing Setup')
            ->assertNoJavaScriptErrors();
    });
});
