<?php

declare(strict_types=1);

use App\Models\AdminDesignation;
use App\Models\User;

it('designates admin emails via artisan', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $this->artisan('admin:designate', [
        'email' => ['Admin@example.com'],
    ])
        ->expectsOutput('Designated admin@example.com as an admin email.')
        ->assertSuccessful();

    expect(AdminDesignation::query()->forEmail('admin@example.com')->exists())->toBeTrue();
    expect($user->isAdmin())->toBeTrue();
});

it('fails when given an invalid email address', function () {
    $this->artisan('admin:designate', [
        'email' => ['not-an-email'],
    ])
        ->expectsOutput('Invalid email address: not-an-email')
        ->assertExitCode(1);
});

it('reports when an email is already designated', function () {
    AdminDesignation::query()->create([
        'email' => 'existing@example.com',
    ]);

    $this->artisan('admin:designate', [
        'email' => ['existing@example.com'],
    ])
        ->expectsOutput('existing@example.com is already designated as an admin email.')
        ->assertSuccessful();
});
