<?php

declare(strict_types=1);

use App\Mail\AdminUsageDigestMail;

test('admin usage digest mail has correct subject', function () {
    $mail = new AdminUsageDigestMail(['total_users' => 100, 'active_users' => 50]);

    expect($mail->envelope()->subject)->toBe('Song Tipper weekly admin usage digest');
});

test('admin usage digest mail has correct content', function () {
    $payload = ['total_users' => 100, 'active_users' => 50, 'flagged_users' => 3];

    $mail = new AdminUsageDigestMail($payload);

    $content = $mail->content();

    expect($content->markdown)->toBe('emails.admin-usage-digest')
        ->and($content->with)->toBe([
            'payload' => $payload,
        ]);
});

test('admin usage digest mail is queued after commit', function () {
    $mail = new AdminUsageDigestMail([]);

    expect($mail->afterCommit)->toBeTrue();
});
