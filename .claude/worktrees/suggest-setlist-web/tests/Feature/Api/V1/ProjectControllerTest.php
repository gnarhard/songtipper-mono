<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\Project;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\PayoutAccountService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
        'name' => 'Original Project',
        'min_tip_cents' => 500,
        'is_accepting_requests' => true,
        'is_accepting_tips' => true,
        'is_accepting_original_requests' => true,
        'show_persistent_queue_strip' => true,
    ]);
    UserPayoutAccount::query()->create([
        'user_id' => $this->owner->id,
        'stripe_account_id' => 'acct_project_owner_1',
        'status' => UserPayoutAccount::STATUS_ENABLED,
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'details_submitted' => true,
        'requirements_currently_due' => [],
        'requirements_past_due' => [],
    ]);
});

describe('Project Index', function () {
    it('lists projects user can access', function () {
        $memberProject = Project::factory()->create();
        $memberProject->addMember($this->owner);

        $otherProject = Project::factory()->create();

        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/me/projects');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'name',
                    'slug',
                    'owner_user_id',
                    'performer_info_url',
                    'performer_profile_image_url',
                    'min_tip_cents',
                    'quick_tip_amounts_cents',
                    'is_accepting_requests',
                    'is_accepting_tips',
                    'is_accepting_original_requests',
                    'show_persistent_queue_strip',
                    'chart_viewport_prefs',
                    'payout_setup_complete',
                    'payout_account_status',
                    'payout_status_reason',
                    'owner' => ['id', 'name'],
                ]],
            ]);

        $projectIds = collect($response->json('data'))->pluck('id');

        expect($projectIds)
            ->toContain($this->project->id)
            ->toContain($memberProject->id)
            ->not->toContain($otherProject->id);
        $indexedProjects = collect($response->json('data'))->keyBy('id');

        expect($indexedProjects[$this->project->id]['quick_tip_amounts_cents'])
            ->toBe([2000, 1500, 1000]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/me/projects');

        $response->assertUnauthorized();
    });
});

describe('Project Create', function () {
    it('allows authenticated users to create projects', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/me/projects', [
            'name' => 'Friday Jazz Night',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Project created successfully.')
            ->assertJsonPath('project.name', 'Friday Jazz Night')
            ->assertJsonPath('project.slug', 'friday-jazz-night')
            ->assertJsonPath('project.owner_user_id', $this->owner->id);

        $this->assertDatabaseHas('projects', [
            'owner_user_id' => $this->owner->id,
            'name' => 'Friday Jazz Night',
            'slug' => 'friday-jazz-night',
        ]);
    });

    it('creates unique slugs for duplicate names', function () {
        Project::factory()->create([
            'name' => 'Friday Jazz Night',
            'slug' => 'friday-jazz-night',
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/me/projects', [
            'name' => 'Friday Jazz Night',
        ]);

        $response->assertCreated()
            ->assertJsonPath('project.slug', 'friday-jazz-night-2');
    });

    it('validates project name', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/me/projects', [
            'name' => '   ',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/me/projects', [
            'name' => 'My Project',
        ]);

        $response->assertUnauthorized();
    });
});

describe('Project Update', function () {
    it('allows owner to update project settings', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'min_tip_cents' => 1000,
            'quick_tip_amounts_cents' => [2400, 1800, 1200],
            'is_accepting_requests' => false,
            'is_accepting_tips' => false,
            'show_persistent_queue_strip' => false,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Project updated successfully.')
            ->assertJsonPath('project.min_tip_cents', 1000)
            ->assertJsonPath('project.quick_tip_amounts_cents', [2400, 1800, 1200])
            ->assertJsonPath('project.is_accepting_requests', false)
            ->assertJsonPath('project.is_accepting_tips', false)
            ->assertJsonPath('project.show_persistent_queue_strip', false);

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'min_tip_cents' => 1000,
            'quick_tip_1_cents' => 2400,
            'quick_tip_2_cents' => 1800,
            'quick_tip_3_cents' => 1200,
            'is_accepting_requests' => false,
            'is_accepting_tips' => false,
            'show_persistent_queue_strip' => false,
        ]);
    });

    it('allows owner to update performer link and original request setting', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'performer_info_url' => 'https://example.com/artist',
            'is_accepting_original_requests' => false,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project.performer_info_url', 'https://example.com/artist')
            ->assertJsonPath('project.is_accepting_original_requests', false);

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'performer_info_url' => 'https://example.com/artist',
            'is_accepting_original_requests' => false,
        ]);
    });

    it('blocks enabling requests when payout setup is incomplete', function () {
        $this->owner->payoutAccount->update([
            'status' => UserPayoutAccount::STATUS_PENDING,
            'charges_enabled' => false,
            'payouts_enabled' => false,
        ]);
        $pendingAccount = $this->owner->payoutAccount->fresh();

        $this->mock(PayoutAccountService::class, function (MockInterface $mock) use ($pendingAccount): void {
            $mock->shouldReceive('refreshAccount')
                ->once()
                ->withArgs(
                    fn (UserPayoutAccount $account): bool => $account->id === $pendingAccount->id
                )
                ->andReturn($pendingAccount);
        });

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'is_accepting_requests' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'payout_setup_incomplete')
            ->assertJsonPath('message', 'Finish payout setup before enabling requests.');
    });

    it('allows enabling requests when tips are disabled and payout setup is incomplete', function () {
        $this->project->update([
            'is_accepting_requests' => false,
            'is_accepting_tips' => false,
        ]);
        $this->owner->payoutAccount->update([
            'status' => UserPayoutAccount::STATUS_PENDING,
            'charges_enabled' => false,
            'payouts_enabled' => false,
        ]);

        $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('refreshAccount');
        });

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'is_accepting_requests' => true,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project.is_accepting_requests', true)
            ->assertJsonPath('project.is_accepting_tips', false);

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'is_accepting_requests' => true,
            'is_accepting_tips' => false,
        ]);
    });

    it('blocks enabling tips while requests are open and payout setup is incomplete', function () {
        $this->project->update([
            'is_accepting_requests' => true,
            'is_accepting_tips' => false,
        ]);
        $this->owner->payoutAccount->update([
            'status' => UserPayoutAccount::STATUS_PENDING,
            'charges_enabled' => false,
            'payouts_enabled' => false,
        ]);
        $pendingAccount = $this->owner->payoutAccount->fresh();

        $this->mock(PayoutAccountService::class, function (MockInterface $mock) use ($pendingAccount): void {
            $mock->shouldReceive('refreshAccount')
                ->once()
                ->withArgs(
                    fn (UserPayoutAccount $account): bool => $account->id === $pendingAccount->id
                )
                ->andReturn($pendingAccount);
        });

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'is_accepting_tips' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'payout_setup_incomplete')
            ->assertJsonPath('message', 'Finish payout setup before enabling requests.');

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'is_accepting_requests' => true,
            'is_accepting_tips' => false,
        ]);
    });

    it('enables requests when stripe refresh confirms payout setup is complete', function () {
        $this->owner->payoutAccount->update([
            'status' => UserPayoutAccount::STATUS_PENDING,
            'status_reason' => 'capabilities_pending',
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => true,
        ]);
        $this->project->update(['is_accepting_requests' => false]);
        $staleAccount = $this->owner->payoutAccount->fresh();

        $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshAccount')
                ->once()
                ->andReturnUsing(function (UserPayoutAccount $account): UserPayoutAccount {
                    $account->update([
                        'status' => UserPayoutAccount::STATUS_ENABLED,
                        'status_reason' => null,
                        'charges_enabled' => true,
                        'payouts_enabled' => true,
                        'requirements_currently_due' => [],
                        'requirements_past_due' => [],
                    ]);

                    return $account->fresh();
                });
        });

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'is_accepting_requests' => true,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project.is_accepting_requests', true)
            ->assertJsonPath('project.payout_setup_complete', true)
            ->assertJsonPath('project.payout_account_status', UserPayoutAccount::STATUS_ENABLED);

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'is_accepting_requests' => true,
        ]);
        $this->assertDatabaseHas('user_payout_accounts', [
            'id' => $staleAccount->id,
            'status' => UserPayoutAccount::STATUS_ENABLED,
            'charges_enabled' => true,
            'payouts_enabled' => true,
        ]);
    });

    it('allows owner to update chart viewport prefs', function () {
        Sanctum::actingAs($this->owner);

        $payload = [
            'chart_viewport_prefs' => [
                'chart_10_page_0' => [
                    'zoom_scale' => 1.6,
                    'offset_dx' => 42.5,
                    'offset_dy' => -18.25,
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", $payload);

        $response->assertSuccessful()
            ->assertJsonPath(
                'project.chart_viewport_prefs.chart_10_page_0.zoom_scale',
                1.6
            )
            ->assertJsonPath(
                'project.chart_viewport_prefs.chart_10_page_0.offset_dx',
                42.5
            )
            ->assertJsonPath(
                'project.chart_viewport_prefs.chart_10_page_0.offset_dy',
                -18.25
            );

        $this->project->refresh();

        expect($this->project->chart_viewport_prefs)->toBe($payload['chart_viewport_prefs']);
    });

    it('allows owner to update project name', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'name' => 'Updated Project Name',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project.name', 'Updated Project Name');

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'name' => 'Updated Project Name',
        ]);
    });

    it('allows partial updates', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'min_tip_cents' => 750,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project.min_tip_cents', 800);

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'min_tip_cents' => 800,
            'is_accepting_requests' => true, // Should remain unchanged
            'is_accepting_tips' => true,
        ]);
    });

    it('rejects non-whole-dollar quick tip amounts', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'quick_tip_amounts_cents' => [2301, 1705, 999],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('quick_tip_amounts_cents');
    });

    it('prevents non-owner from updating project', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'min_tip_cents' => 2000,
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'min_tip_cents' => 500, // Should remain unchanged
        ]);
    });

    it('validates min_tip_cents is an integer', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'min_tip_cents' => 'not-a-number',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('min_tip_cents');
    });

    it('validates min_tip_cents is not negative', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'min_tip_cents' => -100,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('min_tip_cents');
    });

    it('validates is_accepting_requests is boolean', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'is_accepting_requests' => 'not-a-boolean',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('is_accepting_requests');
    });

    it('validates quick_tip_amounts_cents contains three unique values', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'quick_tip_amounts_cents' => [2000, 2000, 1000],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('quick_tip_amounts_cents');
    });

    it('validates quick_tip_amounts_cents stays in descending order', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'quick_tip_amounts_cents' => [1500, 1600, 1000],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('quick_tip_amounts_cents');
    });

    it('validates is_accepting_tips is boolean', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'is_accepting_tips' => 'not-a-boolean',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('is_accepting_tips');
    });

    it('validates show_persistent_queue_strip is boolean', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'show_persistent_queue_strip' => 'not-a-boolean',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('show_persistent_queue_strip');
    });

    it('validates chart_viewport_prefs structure', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'chart_viewport_prefs' => [
                'chart_10_page_0' => [
                    'zoom_scale' => 'invalid',
                    'offset_dx' => 12,
                    'offset_dy' => -4,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('chart_viewport_prefs.chart_10_page_0.zoom_scale');
    });

    it('requires authentication', function () {
        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'min_tip_cents' => 1000,
        ]);

        $response->assertUnauthorized();
    });

    it('uploads performer profile image', function () {
        Storage::fake('public');
        Sanctum::actingAs($this->owner);

        $image = UploadedFile::fake()->image('performer.png');

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/performer-image", [
            'image' => $image,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Performer profile image uploaded successfully.');

        $this->project->refresh();
        expect($this->project->performer_profile_image_path)->not->toBeNull();
        Storage::disk('public')->assertExists($this->project->performer_profile_image_path);
    });

    it('rejects performer image uploads with a disallowed mimetype', function () {
        Storage::fake('public');
        Sanctum::actingAs($this->owner);

        $fakePdf = UploadedFile::fake()->createWithContent(
            'performer.pdf',
            "%PDF-1.4\n%fake pdf content\n%%EOF\n",
        );

        $this->postJson("/api/v1/me/projects/{$this->project->id}/performer-image", [
            'image' => $fakePdf,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        $this->project->refresh();
        expect($this->project->performer_profile_image_path)->toBeNull();
    });
});

describe('Project Delete', function () {
    it('allows owner to delete a project', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}");

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Project deleted successfully.');

        $this->assertDatabaseMissing('projects', [
            'id' => $this->project->id,
        ]);
    });

    it('deletes performer profile image when deleting project', function () {
        Storage::fake('public');

        $imagePath = "performers/{$this->project->id}/performer.png";
        Storage::disk('public')->put($imagePath, 'fake-image-bytes');

        $this->project->update([
            'performer_profile_image_path' => $imagePath,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}");

        $response->assertSuccessful();
        Storage::disk('public')->assertMissing($imagePath);
    });

    it('prevents non-owner from deleting project', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
        ]);
    });

    it('requires authentication', function () {
        $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}");

        $response->assertUnauthorized();
    });

    it('deletes charts and their storage directories when deleting project', function () {
        Storage::fake('public');
        config()->set('filesystems.chart', 'public');

        $chart = Chart::factory()->create([
            'owner_user_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'storage_disk' => 'public',
        ]);

        $chartDir = $chart->getStorageDirectory();
        Storage::disk('public')->put("{$chartDir}/source.pdf", 'pdf-bytes');

        Sanctum::actingAs($this->owner);

        $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}");

        $response->assertSuccessful();
        $this->assertDatabaseMissing('projects', ['id' => $this->project->id]);
        $this->assertDatabaseMissing('charts', ['id' => $chart->id]);
    });
});

describe('Project Update - Profile Image Management', function () {
    it('removes performer profile image on update when flagged', function () {
        Storage::fake('public');

        $imagePath = "performers/{$this->project->id}/performer.png";
        Storage::disk('public')->put($imagePath, 'fake-image-bytes');

        $this->project->update([
            'performer_profile_image_path' => $imagePath,
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'remove_performer_profile_image' => true,
        ]);

        $response->assertSuccessful();
        Storage::disk('public')->assertMissing($imagePath);

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'performer_profile_image_path' => null,
        ]);
    });

    it('replaces existing performer image when uploading new one', function () {
        Storage::fake('public');

        $oldPath = "performers/{$this->project->id}/old-performer.png";
        Storage::disk('public')->put($oldPath, 'old-image-bytes');
        $this->project->update(['performer_profile_image_path' => $oldPath]);

        Sanctum::actingAs($this->owner);

        $newImage = UploadedFile::fake()->image('new-performer.png');

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/performer-image", [
            'image' => $newImage,
        ]);

        $response->assertCreated();
        Storage::disk('public')->assertMissing($oldPath);

        $this->project->refresh();
        expect($this->project->performer_profile_image_path)->not->toBe($oldPath);
    });

    it('prevents non-owner from uploading performer image', function () {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $image = UploadedFile::fake()->image('performer.png');

        $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/performer-image", [
            'image' => $image,
        ]);

        $response->assertForbidden();
    });
});

describe('Project Create - Slug Edge Cases', function () {
    it('uses project as slug when name has no alphanumeric characters', function () {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/me/projects', [
            'name' => '!!!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('project.slug', 'project');
    });
});

describe('Project Update - Payout Refresh Failure', function () {
    it('returns payout_setup_incomplete when stripe refresh throws', function () {
        $this->project->update([
            'is_accepting_requests' => false,
            'is_accepting_tips' => false,
        ]);
        $this->owner->payoutAccount->update([
            'status' => UserPayoutAccount::STATUS_PENDING,
            'charges_enabled' => false,
            'payouts_enabled' => false,
        ]);

        $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshAccount')
                ->once()
                ->andThrow(new RuntimeException('Stripe connection failed'));
        });

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'is_accepting_requests' => true,
            'is_accepting_tips' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'payout_setup_incomplete');
    });

    it('returns payout_setup_incomplete when no payout account exists', function () {
        $this->project->update([
            'is_accepting_requests' => false,
            'is_accepting_tips' => false,
        ]);
        $this->owner->payoutAccount->delete();
        $this->owner->unsetRelation('payoutAccount');

        $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('refreshAccount');
        });

        Sanctum::actingAs($this->owner);

        $response = $this->putJson("/api/v1/me/projects/{$this->project->id}", [
            'is_accepting_requests' => true,
            'is_accepting_tips' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'payout_setup_incomplete');
    });
});
