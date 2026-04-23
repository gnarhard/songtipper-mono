<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs in with email and password and returns a token', function () {
    $user = User::factory()->create([
        'email' => 'alice@example.com',
        'password' => Hash::make('secret-password'),
        'instrument_type' => 'vocals',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'alice@example.com',
        'password' => 'secret-password',
    ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'token',
            'accessBundle' => [
                'projects',
            ],
            'user' => [
                'id',
                'name',
                'email',
                'instrument_type',
            ],
        ])
        ->assertJsonPath('user.instrument_type', 'vocals');
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'alice@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'alice@example.com',
        'password' => 'wrong',
    ])
        ->assertStatus(401)
        ->assertJson([
            'message' => 'Invalid credentials.',
        ]);
});

it('rejects unverified users', function () {
    User::factory()->unverified()->create([
        'email' => 'alice@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'alice@example.com',
        'password' => 'secret-password',
    ])
        ->assertForbidden()
        ->assertJson([
            'message' => 'Please verify your email address before signing in.',
        ]);
});

it('logs out the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-device');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertSuccessful()
        ->assertJson([
            'message' => 'Logged out successfully.',
        ]);

    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('requires authentication to log out', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
});

it('returns viewer-aware entitlements for a pro owner on login', function () {
    $user = billingReadyUser([
        'email' => 'pro@example.com',
        'password' => Hash::make('secret-password'),
        'billing_plan' => User::BILLING_PLAN_PRO_YEARLY,
    ]);

    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'pro@example.com',
        'password' => 'secret-password',
    ])->assertSuccessful();

    $projectData = collect($response->json('accessBundle.projects'))
        ->firstWhere('id', $project->id);

    expect($projectData)->not->toBeNull();
    expect($projectData['entitlements']['plan_tier'])->toBe('pro');
    expect($projectData['entitlements']['can_view_owner_stats'])->toBeTrue();
    expect($projectData['entitlements']['can_access_queue'])->toBeTrue();
    expect($projectData['entitlements']['can_access_history'])->toBeTrue();
    expect($projectData['entitlements']['can_view_wallet'])->toBeTrue();
});
