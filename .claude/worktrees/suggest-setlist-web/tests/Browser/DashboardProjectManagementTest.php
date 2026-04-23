<?php

declare(strict_types=1);

use App\Models\Project;

describe('Dashboard project management browser flows', function () {
    it('creates a project from the dashboard form', function () {
        $user = billingReadyUser();

        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertSee('No projects yet')
            ->click('Create New Project')
            ->fill('name', 'Browser Created Project')
            ->fill('slug', 'browser-created-project')
            ->fill('minTipDollars', '8')
            ->fill('quickTip1Dollars', '24')
            ->fill('quickTip2Dollars', '17')
            ->fill('quickTip3Dollars', '11')
            ->fill('performerInfoUrl', 'https://example.com/performer')
            ->press('Create Project')
            ->wait(1)
            ->assertSee('Browser Created Project')
            ->assertSee('Minimum tip: $8')
            ->assertSee('Artist info link')
            ->assertNoJavaScriptErrors();

        expect(
            Project::query()
                ->where('owner_user_id', $user->id)
                ->where('slug', 'browser-created-project')
                ->where('min_tip_cents', 800)
                ->where('quick_tip_1_cents', 2400)
                ->where('quick_tip_2_cents', 1700)
                ->where('quick_tip_3_cents', 1100)
                ->where('performer_info_url', 'https://example.com/performer')
                ->exists()
        )->toBeTrue();
    });

    it('edits an existing project from the dashboard modal', function () {
        $user = billingReadyUser();
        $project = Project::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'Before Edit Project',
            'slug' => 'before-edit-project',
            'min_tip_cents' => 500,
            'performer_info_url' => null,
        ]);

        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertSee('Before Edit Project')
            ->click('[title="Edit project"]')
            ->assertSee('Edit Project')
            ->fill('edit-name', 'After Edit Project')
            ->fill('edit-slug', 'after-edit-project')
            ->fill('edit-minTipDollars', '12')
            ->fill('edit-quickTip1Dollars', '28')
            ->fill('edit-quickTip2Dollars', '21')
            ->fill('edit-quickTip3Dollars', '14')
            ->fill('edit-performerInfoUrl', 'https://example.com/updated')
            ->press('Save Changes')
            ->wait(1)
            ->assertSee('After Edit Project')
            ->assertSee('Minimum tip: $12')
            ->assertSee('Artist info link')
            ->assertNoJavaScriptErrors();

        expect(
            $project->fresh()->only([
                'name',
                'slug',
                'min_tip_cents',
                'quick_tip_1_cents',
                'quick_tip_2_cents',
                'quick_tip_3_cents',
                'performer_info_url',
            ])
        )->toBe([
            'name' => 'After Edit Project',
            'slug' => 'after-edit-project',
            'min_tip_cents' => 1200,
            'quick_tip_1_cents' => 2800,
            'quick_tip_2_cents' => 2100,
            'quick_tip_3_cents' => 1400,
            'performer_info_url' => 'https://example.com/updated',
        ]);
    });
});
