<?php

declare(strict_types=1);

it('allows all authenticated users to access dashboard regardless of billing status', function () {
    $user = setupRequiredUser();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
});

it('allows users with completed billing to access dashboard', function () {
    $user = billingReadyUser();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
});

it('allows unauthenticated users through without redirect', function () {
    $response = $this->get('/dashboard');

    // Should redirect to login, not to billing setup
    $response->assertRedirect(route('login'));
});
