<?php

declare(strict_types=1);

use App\Models\AdminDesignation;

describe('Admin complimentary access browser flows', function () {
    it('renders the admin access page with complimentary access form', function () {
        $user = billingReadyUser();
        AdminDesignation::create(['email' => $user->email]);

        $this->actingAs($user);

        $page = visit('/admin/access');

        $page->assertSee('Complimentary Access')
            ->assertNoJavaScriptErrors();
    });

    it('blocks non-admin users from accessing admin access page', function () {
        $user = billingReadyUser();

        $this->actingAs($user);

        $page = visit('/admin/access');

        $page->assertDontSee('Complimentary Access')
            ->assertNoJavaScriptErrors();
    });
});
