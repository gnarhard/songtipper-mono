<?php

declare(strict_types=1);

use App\Models\User;

describe('404 page', function () {
    it('returns a 404 status for an unknown route', function () {
        $this->get('/this-route-definitely-does-not-exist')
            ->assertNotFound();
    });

    it('renders the styled 404 view with music-themed copy', function () {
        $this->get('/this-route-definitely-does-not-exist')
            ->assertNotFound()
            ->assertSee('Off the setlist')
            ->assertSee("This track isn't in our repertoire.", false)
            ->assertSee('Back to home')
            ->assertSee('Read the blog');
    });

    it('shows guest nav links when unauthenticated', function () {
        $this->get('/this-route-definitely-does-not-exist')
            ->assertNotFound()
            ->assertSee('Login')
            ->assertSee('Register');
    });

    it('shows dashboard link when authenticated', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/this-route-definitely-does-not-exist')
            ->assertNotFound()
            ->assertSee('Dashboard');
    });
});
