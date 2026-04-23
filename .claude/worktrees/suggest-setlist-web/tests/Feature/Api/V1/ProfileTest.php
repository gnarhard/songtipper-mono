<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the authenticated user profile', function () {
    $user = User::factory()->create([
        'instrument_type' => 'bass',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/profile')
        ->assertSuccessful()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.instrument_type', 'bass');
});

it('updates the authenticated user instrument type', function () {
    $user = User::factory()->create([
        'instrument_type' => 'vocals',
    ]);

    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me/profile', [
        'instrument_type' => 'drums',
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Profile updated successfully.')
        ->assertJsonPath('user.instrument_type', 'drums');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'instrument_type' => 'drums',
    ]);
});

it('allows clearing the authenticated user instrument type', function () {
    $user = User::factory()->create([
        'instrument_type' => 'guitar',
    ]);

    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me/profile', [
        'instrument_type' => null,
    ])
        ->assertSuccessful()
        ->assertJsonPath('user.instrument_type', null);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'instrument_type' => null,
    ]);
});

it('validates instrument type updates', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me/profile', [
        'instrument_type' => 'kazoo',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('instrument_type');
});

it('returns the secondary instrument type in the profile', function () {
    $user = User::factory()->create([
        'instrument_type' => 'guitar',
        'secondary_instrument_type' => 'piano',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/profile')
        ->assertSuccessful()
        ->assertJsonPath('user.instrument_type', 'guitar')
        ->assertJsonPath('user.secondary_instrument_type', 'piano');
});

it('updates the secondary instrument type', function () {
    $user = User::factory()->create([
        'secondary_instrument_type' => null,
    ]);

    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me/profile', [
        'secondary_instrument_type' => 'keyboard',
    ])
        ->assertSuccessful()
        ->assertJsonPath('user.secondary_instrument_type', 'keyboard');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'secondary_instrument_type' => 'keyboard',
    ]);
});

it('allows clearing the secondary instrument type', function () {
    $user = User::factory()->create([
        'secondary_instrument_type' => 'bass',
    ]);

    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me/profile', [
        'secondary_instrument_type' => null,
    ])
        ->assertSuccessful()
        ->assertJsonPath('user.secondary_instrument_type', null);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'secondary_instrument_type' => null,
    ]);
});

it('validates secondary instrument type updates', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me/profile', [
        'secondary_instrument_type' => 'kazoo',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('secondary_instrument_type');
});

it('requires authentication to read the profile', function () {
    $this->getJson('/api/v1/me/profile')
        ->assertUnauthorized();
});

it('requires authentication to update the profile', function () {
    $this->patchJson('/api/v1/me/profile', [
        'instrument_type' => 'drums',
    ])
        ->assertUnauthorized();
});
