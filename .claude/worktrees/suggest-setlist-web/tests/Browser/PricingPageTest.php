<?php

declare(strict_types=1);

describe('Pricing page browser flows', function () {
    it('shows three pricing tiers on the home page', function () {
        $page = visit('/');

        $page->assertSee('Free')
            ->assertSee('Basic')
            ->assertSee('Pro')
            ->assertSee('Get Started Free')
            ->assertNoJavaScriptErrors();
    });

    it('shows band sync as a feature', function () {
        $page = visit('/');

        $page->assertSee('Band Sync')
            ->assertNoJavaScriptErrors();
    });
});
