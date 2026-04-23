<?php

declare(strict_types=1);

describe('Profile management browser flows', function () {
    it('updates the user name from the profile page', function () {
        $user = billingReadyUser([
            'name' => 'Original Name',
        ]);

        $this->actingAs($user);

        $page = visit('/profile');

        $page->assertSee('Profile Information')
            ->assertSee('Original Name')
            ->fill('#name', 'Updated Performer')
            ->press('Save')
            ->assertPathIs('/profile')
            ->assertSee('Saved.')
            ->assertNoJavaScriptErrors();

        expect($user->fresh()->name)->toBe('Updated Performer');
    });

    it('updates the user email and triggers re-verification', function () {
        $user = billingReadyUser([
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user);

        $page = visit('/profile');

        $page->assertSee('original@example.com')
            ->fill('#email', 'newemail@example.com')
            ->press('Save')
            ->assertPathIs('/profile')
            ->assertSee('Saved.')
            ->assertNoJavaScriptErrors();

        $freshUser = $user->fresh();
        expect($freshUser->email)->toBe('newemail@example.com');
        expect($freshUser->email_verified_at)->toBeNull();
    });

    it('renders the password update section', function () {
        $user = billingReadyUser();

        $this->actingAs($user);

        $page = visit('/profile');

        $page->assertSee('Update Password')
            ->assertSee('Ensure your account is using a long, random password to stay secure.')
            ->assertNoJavaScriptErrors();
    });

    it('renders the delete account section with warning', function () {
        $user = billingReadyUser();

        $this->actingAs($user);

        $page = visit('/profile');

        $page->assertSee('Delete Account')
            ->assertSee('Once your account is deleted, all of its resources and data will be permanently deleted.')
            ->assertNoJavaScriptErrors();
    });
});
