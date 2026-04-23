<?php

declare(strict_types=1);

use App\Models\Project;

describe('Dashboard navigation browser flows', function () {
    it('shows the dashboard with project list for billing-ready users', function () {
        $user = billingReadyUser();
        Project::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'My First Project',
        ]);
        Project::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'My Second Project',
        ]);

        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertSee('My First Project')
            ->assertSee('My Second Project')
            ->assertNoJavaScriptErrors();
    });

    it('shows empty state when user has no projects', function () {
        $user = billingReadyUser();

        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertSee('No projects yet')
            ->assertNoJavaScriptErrors();
    });

    it('navigates to the profile page', function () {
        $user = billingReadyUser();
        Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $this->actingAs($user);

        $page = visit('/profile');

        $page->assertSee('Profile Information')
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertNoJavaScriptErrors();
    });

    it('navigates to the billing page', function () {
        $user = billingReadyUser();
        Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $this->actingAs($user);

        $page = visit('/dashboard/billing');

        $page->assertSee('Billing')
            ->assertNoJavaScriptErrors();
    });

    it('opens the user menu dropdown from the billing page', function () {
        $user = billingReadyUser();

        $this->actingAs($user);

        $page = visit('/dashboard/billing');

        $page->assertDontSee('Log Out')
            ->click('[data-test="nav-user-menu-trigger"]')
            ->assertSee('Profile')
            ->assertSee('Log Out')
            ->assertNoJavaScriptErrors();
    });

    it('shows project slug link for sharing', function () {
        $user = billingReadyUser();
        Project::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'Shareable Project',
            'slug' => 'shareable-project',
        ]);

        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertSee('Shareable Project')
            ->assertSee('shareable-project')
            ->assertNoJavaScriptErrors();
    });
});
