<?php

declare(strict_types=1);

describe('Static pages browser flows', function () {
    it('renders the home page with marketing content', function () {
        $page = visit('/');

        $page->assertSee('Song Tipper')
            ->assertNoJavaScriptErrors();
    });

    it('renders the terms of service page', function () {
        $page = visit('/terms');

        $page->assertSee('Terms')
            ->assertNoJavaScriptErrors();
    });

    it('renders the privacy policy page', function () {
        $page = visit('/privacy');

        $page->assertSee('Privacy')
            ->assertNoJavaScriptErrors();
    });

    it('renders the EULA page', function () {
        $page = visit('/eula');

        $page->assertSee('End User License Agreement')
            ->assertNoJavaScriptErrors();
    });

    it('redirects unauthenticated users from dashboard to login', function () {
        $page = visit('/dashboard');

        $page->assertPathIs('/login')
            ->assertNoJavaScriptErrors();
    });

    it('redirects unauthenticated users from profile to login', function () {
        $page = visit('/profile');

        $page->assertPathIs('/login')
            ->assertNoJavaScriptErrors();
    });
});
