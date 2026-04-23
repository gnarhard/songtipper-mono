<?php

declare(strict_types=1);

use App\Mail\UsageWarningMail;
use App\Models\User;

test('usage warning mail has correct subject', function () {
    $user = User::factory()->create();

    $mail = new UsageWarningMail($user, 'High usage detected', ['requests' => 1000]);

    expect($mail->envelope()->subject)->toBe('Song Tipper usage warning');
});

test('usage warning mail has correct content', function () {
    $user = User::factory()->create();
    $warningMessage = 'High usage detected';
    $usagePayload = ['requests' => 1000, 'period' => 'daily'];

    $mail = new UsageWarningMail($user, $warningMessage, $usagePayload);

    $content = $mail->content();

    expect($content->markdown)->toBe('emails.usage-warning')
        ->and($content->with)->toBe([
            'user' => $user,
            'warningMessage' => $warningMessage,
            'usagePayload' => $usagePayload,
        ]);
});

test('usage warning mail is queued after commit', function () {
    $user = User::factory()->create();

    $mail = new UsageWarningMail($user, 'Warning', []);

    expect($mail->afterCommit)->toBeTrue();
});
