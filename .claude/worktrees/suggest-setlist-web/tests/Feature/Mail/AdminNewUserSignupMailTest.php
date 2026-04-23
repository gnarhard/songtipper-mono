<?php

declare(strict_types=1);

use App\Mail\AdminNewUserSignupMail;
use App\Models\User;

test('admin new user signup mail has correct subject', function () {
    $user = User::factory()->create();

    $mail = new AdminNewUserSignupMail($user);

    expect($mail->envelope()->subject)->toBe('Song Tipper: New user signup');
});

test('admin new user signup mail has correct content', function () {
    $user = User::factory()->create();

    $mail = new AdminNewUserSignupMail($user);

    $content = $mail->content();

    expect($content->markdown)->toBe('emails.admin-new-user-signup')
        ->and($content->with)->toBe([
            'user' => $user,
        ]);
});

test('admin new user signup mail is queued after commit', function () {
    $user = User::factory()->create();

    $mail = new AdminNewUserSignupMail($user);

    expect($mail->afterCommit)->toBeTrue();
});
