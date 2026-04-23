<?php

declare(strict_types=1);

use App\Mail\AdminUsageAlertMail;
use App\Models\AccountUsageFlag;
use App\Models\User;

test('admin usage alert mail has correct subject', function () {
    $user = User::factory()->create();
    $flag = AccountUsageFlag::factory()->create(['user_id' => $user->id]);

    $mail = new AdminUsageAlertMail($user, $flag);

    expect($mail->envelope()->subject)->toBe('Song Tipper admin usage alert');
});

test('admin usage alert mail has correct content', function () {
    $user = User::factory()->create();
    $flag = AccountUsageFlag::factory()->create(['user_id' => $user->id]);

    $mail = new AdminUsageAlertMail($user, $flag);

    $content = $mail->content();

    expect($content->markdown)->toBe('emails.admin-usage-alert')
        ->and($content->with)->toBe([
            'user' => $user,
            'flag' => $flag,
        ]);
});

test('admin usage alert mail is queued after commit', function () {
    $user = User::factory()->create();
    $flag = AccountUsageFlag::factory()->create(['user_id' => $user->id]);

    $mail = new AdminUsageAlertMail($user, $flag);

    expect($mail->afterCommit)->toBeTrue();
});
