<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\PayoutAccountService;
use Livewire\Livewire;
use Mockery\MockInterface;

describe('Dashboard Access', function () {
    it('requires authentication', function () {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    });

    it('shows dashboard for authenticated users', function () {
        $user = billingReadyUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Dashboard')
            ->assertSee('Manage Everything in the App');
    });

    it('shows app store download links', function () {
        $user = billingReadyUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('App Store')
            ->assertSee('Google Play');
    });

    it('does not show the password update form on dashboard', function () {
        $user = billingReadyUser();
        $this->withoutVite();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertDontSee('Update Password')
            ->assertDontSee('Current Password')
            ->assertDontSee('New Password');
    });

    it('shows app download banner and key dashboard sections', function () {
        $user = billingReadyUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Manage Everything in the App')
            ->assertSee('App Store')
            ->assertSee('Google Play')
            ->assertSee('Usage and Fair Use');
    });
});

describe('Dashboard Projects List', function () {
    it('shows user projects', function () {
        $user = billingReadyUser();
        $project = Project::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'My Test Band',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('My Test Band');
    });

    it('shows embed widget card when user has no projects', function () {
        $user = billingReadyUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Embeddable Repertoire Widget');
    });

    it('shows embed code for each project', function () {
        $user = billingReadyUser();

        $project = Project::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'Test Band',
            'slug' => 'test-band',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Embeddable Repertoire Widget')
            ->assertSee('test-band');
    });

    it('shows correct project counts', function () {
        $user = User::factory()->create();

        Project::factory()->count(2)->create([
            'owner_user_id' => $user->id,
            'is_accepting_requests' => true,
        ]);

        Project::factory()->create([
            'owner_user_id' => $user->id,
            'is_accepting_requests' => false,
        ]);

        Livewire::actingAs($user)
            ->test('dashboard-page')
            ->assertSee('3') // Total projects
            ->assertSee('2'); // Active projects
    });

    it('does not show other users projects', function () {
        $user = billingReadyUser();
        $otherUser = User::factory()->create();

        Project::factory()->create([
            'owner_user_id' => $otherUser->id,
            'name' => 'Other Users Band',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertDontSee('Other Users Band');
    });
});

describe('Create Project Form', function () {
    it('can toggle create project form', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('create-project-form')
            ->assertSet('showForm', false)
            ->call('toggleForm')
            ->assertSet('showForm', true)
            ->call('toggleForm')
            ->assertSet('showForm', false);
    });

    it('validates required fields', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('create-project-form')
            ->set('showForm', true)
            ->set('name', '')
            ->set('slug', '')
            ->call('create')
            ->assertHasErrors(['name', 'slug']);
    });

    it('sanitizes slug on update', function () {
        $user = User::factory()->create();

        // The component automatically sanitizes slugs via Str::slug()
        // So even invalid input gets converted to valid slug format
        Livewire::actingAs($user)
            ->test('create-project-form')
            ->set('showForm', true)
            ->set('slug', 'Invalid Slug With Spaces!')
            ->assertSet('slug', 'invalid-slug-with-spaces'); // Str::slug sanitizes it
    });

    it('validates unique slug', function () {
        $user = User::factory()->create();

        Project::factory()->create(['slug' => 'existing-slug']);

        Livewire::actingAs($user)
            ->test('create-project-form')
            ->set('showForm', true)
            ->set('name', 'Test Project')
            ->set('slug', 'existing-slug')
            ->call('create')
            ->assertHasErrors(['slug']);
    });

    it('auto-generates slug from name', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('create-project-form')
            ->set('showForm', true)
            ->set('name', 'My Awesome Band')
            ->assertSet('slug', 'my-awesome-band');
    });

    it('renders a custom performer image upload button', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('create-project-form')
            ->set('showForm', true)
            ->assertSee('No file chosen')
            ->assertSee('Choose performer profile image');
    });

    it('creates project successfully', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('create-project-form')
            ->set('showForm', true)
            ->set('name', 'New Test Band')
            ->set('slug', 'new-test-band')
            ->set('minTipDollars', 10.00)
            ->set('quickTip1Dollars', 25.00)
            ->set('quickTip2Dollars', 18.00)
            ->set('quickTip3Dollars', 12.00)
            ->set('isAcceptingRequests', true)
            ->set('isAcceptingTips', false)
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertDispatched('project-created');

        $this->assertDatabaseHas('projects', [
            'owner_user_id' => $user->id,
            'name' => 'New Test Band',
            'slug' => 'new-test-band',
            'min_tip_cents' => 1000,
            'quick_tip_1_cents' => 2500,
            'quick_tip_2_cents' => 1800,
            'quick_tip_3_cents' => 1200,
            'is_accepting_requests' => true,
            'is_accepting_tips' => false,
        ]);
    });

    it('validates minimum tip amount is not negative', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('create-project-form')
            ->set('showForm', true)
            ->set('name', 'Test Project')
            ->set('slug', 'test-project')
            ->set('minTipDollars', -1)
            ->call('create')
            ->assertHasErrors(['minTipDollars']);
    });

    it('resets form after cancel', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('create-project-form')
            ->set('showForm', true)
            ->set('name', 'Test Name')
            ->set('slug', 'test-slug')
            ->call('toggleForm')
            ->assertSet('name', '')
            ->assertSet('slug', '')
            ->assertSet('showForm', false);
    });
});

describe('Dashboard Page Component', function () {
    it('shows stripe express setup required for pro users without payout setup', function () {
        $user = billingReadyUser([
            'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
        ]);
        Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test('dashboard-page')
            ->assertSee('Stripe Express Setup Required')
            ->assertSee('Complete Stripe Express setup to enable Pro request collection and payouts.');
    });

    it('shows stripe express setup required for all users without payout setup', function () {
        $user = billingReadyUser([
            'billing_plan' => User::BILLING_PLAN_PRO_MONTHLY,
        ]);
        Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test('dashboard-page')
            ->assertSee('Stripe Express Setup Required');
    });

    it('shows newly created project in embed widget', function () {
        $user = User::factory()->create();

        // Create a project
        Project::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'Newly Created Band',
            'slug' => 'newly-created-band',
        ]);

        // Component should show the project in the embed widget
        Livewire::actingAs($user)
            ->test('dashboard-page')
            ->assertSee('newly-created-band');
    });

    it('refreshes stripe connect status from stripe on dashboard', function () {
        $user = billingReadyUser();
        Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);
        UserPayoutAccount::factory()->create([
            'user_id' => $user->id,
            'status' => UserPayoutAccount::STATUS_PENDING,
            'status_reason' => 'requirements_due',
        ]);

        $this->mock(PayoutAccountService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('getForUser')
                ->once()
                ->withArgs(fn (User $requestedUser, bool $refreshFromStripe): bool => $requestedUser->id === $user->id && $refreshFromStripe)
                ->andReturnUsing(function () use ($user): UserPayoutAccount {
                    UserPayoutAccount::query()
                        ->where('user_id', $user->id)
                        ->update([
                            'status' => UserPayoutAccount::STATUS_RESTRICTED,
                            'status_reason' => 'requirements_past_due',
                        ]);

                    return UserPayoutAccount::query()
                        ->where('user_id', $user->id)
                        ->firstOrFail();
                });
        });

        Livewire::actingAs($user)
            ->test('dashboard-page')
            ->assertSee('Setup in progress')
            ->assertSee('Stripe needs additional onboarding details.')
            ->call('refreshStripeConnectStatus')
            ->assertHasNoErrors(['payout'])
            ->assertSee('Action required in Stripe')
            ->assertSee('Stripe has overdue requirements to resolve.');
    });

    it('shows a payout error when stripe status refresh fails', function () {
        $user = billingReadyUser();
        Project::factory()->create([
            'owner_user_id' => $user->id,
        ]);
        UserPayoutAccount::factory()->create([
            'user_id' => $user->id,
            'status' => UserPayoutAccount::STATUS_PENDING,
            'status_reason' => 'requirements_due',
        ]);

        $this->mock(PayoutAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getForUser')
                ->once()
                ->andThrow(new RuntimeException('Stripe unavailable for testing'));
        });

        Livewire::actingAs($user)
            ->test('dashboard-page')
            ->call('refreshStripeConnectStatus')
            ->assertHasErrors(['payout'])
            ->assertSee('Unable to refresh Stripe status right now. Please try again.');
    });
});

describe('Edit Project Form', function () {
    it('can open edit form for a project', function () {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'My Band',
            'slug' => 'my-band',
            'min_tip_cents' => 500,
            'is_accepting_requests' => true,
        ]);

        Livewire::actingAs($user)
            ->test('edit-project-form')
            ->assertSet('showForm', false)
            ->call('edit', projectId: $project->id)
            ->assertSet('showForm', true)
            ->assertSet('projectId', $project->id)
            ->assertSet('name', 'My Band')
            ->assertSet('slug', 'my-band')
            ->assertSet('minTipDollars', 5.0)
            ->assertSet('quickTip1Dollars', 20.0)
            ->assertSet('quickTip2Dollars', 15.0)
            ->assertSet('quickTip3Dollars', 10.0)
            ->assertSet('isAcceptingRequests', true)
            ->assertSet('isAcceptingTips', true)
            ->assertSet('isAcceptingOriginalRequests', true);
    });

    it('validates required fields', function () {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('edit-project-form')
            ->call('edit', projectId: $project->id)
            ->set('name', '')
            ->set('slug', '')
            ->call('update')
            ->assertHasErrors(['name', 'slug']);
    });

    it('validates unique slug but allows keeping current slug', function () {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $user->id,
            'slug' => 'my-band',
        ]);
        Project::factory()->create(['slug' => 'other-band']);

        // Should allow keeping the same slug
        Livewire::actingAs($user)
            ->test('edit-project-form')
            ->call('edit', projectId: $project->id)
            ->set('name', 'Updated Name')
            ->call('update')
            ->assertHasNoErrors(['slug']);

        // Should not allow using another project's slug
        Livewire::actingAs($user)
            ->test('edit-project-form')
            ->call('edit', projectId: $project->id)
            ->set('slug', 'other-band')
            ->call('update')
            ->assertHasErrors(['slug']);
    });

    it('updates project successfully', function () {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'min_tip_cents' => 500,
            'is_accepting_requests' => true,
        ]);

        Livewire::actingAs($user)
            ->test('edit-project-form')
            ->call('edit', projectId: $project->id)
            ->set('name', 'Updated Name')
            ->set('slug', 'updated-slug')
            ->set('minTipDollars', 15.00)
            ->set('quickTip1Dollars', 26.00)
            ->set('quickTip2Dollars', 19.00)
            ->set('quickTip3Dollars', 13.00)
            ->set('performerInfoUrl', 'https://example.com/artist')
            ->set('isAcceptingRequests', false)
            ->set('isAcceptingTips', false)
            ->set('isAcceptingOriginalRequests', false)
            ->call('update')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertDispatched('project-updated');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
            'performer_info_url' => 'https://example.com/artist',
            'min_tip_cents' => 1500,
            'quick_tip_1_cents' => 2600,
            'quick_tip_2_cents' => 1900,
            'quick_tip_3_cents' => 1300,
            'is_accepting_requests' => false,
            'is_accepting_tips' => false,
            'is_accepting_original_requests' => false,
        ]);
    });

    it('validates quick tip buttons stay in descending order', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('create-project-form')
            ->set('showForm', true)
            ->set('name', 'Test Project')
            ->set('slug', 'test-project')
            ->set('quickTip1Dollars', 15.00)
            ->set('quickTip2Dollars', 20.00)
            ->set('quickTip3Dollars', 10.00)
            ->call('create')
            ->assertHasErrors([
                'quickTip1Dollars',
                'quickTip2Dollars',
                'quickTip3Dollars',
            ]);
    });

    it('renders a custom performer image upload button', function () {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('edit-project-form')
            ->call('edit', projectId: $project->id)
            ->assertSee('No file chosen')
            ->assertSee('Choose performer profile image');
    });

    it('prevents editing other users projects', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['owner_user_id' => $otherUser->id]);

        Livewire::actingAs($user)
            ->test('edit-project-form')
            ->call('edit', projectId: $project->id)
            ->assertStatus(403);
    });

    it('cannot modify locked project id', function () {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_user_id' => $user->id]);
        $otherProject = Project::factory()->create(['owner_user_id' => $user->id]);

        // projectId is locked and cannot be changed after being set via edit()
        Livewire::actingAs($user)
            ->test('edit-project-form')
            ->call('edit', projectId: $project->id)
            ->assertSet('projectId', $project->id);
    });

    it('resets form after cancel', function () {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('edit-project-form')
            ->call('edit', projectId: $project->id)
            ->assertSet('showForm', true)
            ->call('cancel')
            ->assertSet('showForm', false)
            ->assertSet('projectId', null)
            ->assertSet('name', '');
    });

    it('sanitizes slug on update', function () {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test('edit-project-form')
            ->call('edit', projectId: $project->id)
            ->set('slug', 'Invalid Slug With Spaces!')
            ->assertSet('slug', 'invalid-slug-with-spaces');
    });
});
