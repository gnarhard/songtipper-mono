<?php

declare(strict_types=1);

use App\Mail\InactiveAccountArchivalWarningMail;
use App\Models\User;
use Carbon\CarbonImmutable;

test('inactive account archival warning mail has correct subject', function () {
    $user = User::factory()->create();
    $lastActivityAt = CarbonImmutable::now()->subMonths(6);
    $archiveAt = CarbonImmutable::now()->addDays(30);

    $mail = new InactiveAccountArchivalWarningMail($user, $lastActivityAt, $archiveAt);

    expect($mail->envelope()->subject)->toBe('Song Tipper archival notice');
});

test('inactive account archival warning mail has correct content', function () {
    $user = User::factory()->create();
    $lastActivityAt = CarbonImmutable::now()->subMonths(6);
    $archiveAt = CarbonImmutable::now()->addDays(30);

    $mail = new InactiveAccountArchivalWarningMail($user, $lastActivityAt, $archiveAt);

    $content = $mail->content();

    expect($content->markdown)->toBe('emails.inactive-account-archival-warning')
        ->and($content->with)->toBe([
            'user' => $user,
            'lastActivityAt' => $lastActivityAt,
            'archiveAt' => $archiveAt,
        ]);
});

test('inactive account archival warning mail is queued after commit', function () {
    $user = User::factory()->create();
    $lastActivityAt = CarbonImmutable::now()->subMonths(6);
    $archiveAt = CarbonImmutable::now()->addDays(30);

    $mail = new InactiveAccountArchivalWarningMail($user, $lastActivityAt, $archiveAt);

    expect($mail->afterCommit)->toBeTrue();
});
