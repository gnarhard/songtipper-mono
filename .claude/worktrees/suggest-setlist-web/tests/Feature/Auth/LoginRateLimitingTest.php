<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'throttle@example.com',
        'password' => Hash::make('correct-password'),
    ]);
});

it('locks out after too many failed web login attempts', function () {
    // Make 5 failed attempts to trigger rate limiter
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', [
            'email' => 'throttle@example.com',
            'password' => 'wrong-password',
        ]);
    }

    // 6th attempt should be rate limited even with the correct password
    $response = $this->post('/login', [
        'email' => 'throttle@example.com',
        'password' => 'correct-password',
    ]);

    $response->assertSessionHasErrors('email');
});

it('clears rate limiter after successful web login', function () {
    // Make a few failed attempts
    for ($i = 0; $i < 3; $i++) {
        $this->post('/login', [
            'email' => 'throttle@example.com',
            'password' => 'wrong-password',
        ]);
    }

    // Login successfully (should clear the limiter)
    $this->post('/login', [
        'email' => 'throttle@example.com',
        'password' => 'correct-password',
    ]);

    $this->post('/logout');

    // Make a few more failed attempts — since we cleared, it shouldn't
    // be at the limit yet
    for ($i = 0; $i < 4; $i++) {
        $this->post('/login', [
            'email' => 'throttle@example.com',
            'password' => 'wrong-password',
        ]);
    }

    // 5th failed attempt should still work (not locked out)
    $response = $this->post('/login', [
        'email' => 'throttle@example.com',
        'password' => 'correct-password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});
