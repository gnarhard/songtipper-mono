<?php

declare(strict_types=1);

use App\Mail\AdminUsageAlertMail;
use App\Mail\AdminUsageDigestMail;
use App\Models\AdminDesignation;
use App\Models\Project;
use App\Models\User;
use App\Services\AccountUsageService;
use App\Services\AdminRecipientService;
use Illuminate\Support\Facades\Mail;

it('deduplicates admin recipients across stored and configured addresses', function () {
    config()->set('mail.admin_address', ' Admin@example.com ');

    AdminDesignation::factory()->create([
        'email' => 'admin@example.com',
    ]);
    AdminDesignation::factory()->create([
        'email' => 'ops@example.com',
    ]);

    $recipients = app(AdminRecipientService::class)->recipients();

    expect($recipients)->toBe([
        'admin@example.com',
        'ops@example.com',
    ]);
});

it('queues alert and weekly digest emails once per deduplicated admin recipient list', function () {
    Mail::fake();
    config()->set('mail.admin_address', 'ADMIN@example.com');

    AdminDesignation::factory()->create([
        'email' => 'admin@example.com',
    ]);
    AdminDesignation::factory()->create([
        'email' => 'ops@example.com',
    ]);

    $owner = User::factory()->create();
    Project::factory()->create([
        'owner_user_id' => $owner->id,
    ]);

    $service = app(AccountUsageService::class);
    $service->openUsageFlag(
        $owner,
        'ai_spike',
        'review',
        'Daily AI usage spiked above the normal range.',
    );
    $service->sendWeeklyDigest();

    Mail::assertQueued(AdminUsageAlertMail::class, function (AdminUsageAlertMail $mail): bool {
        return $mail->hasTo('admin@example.com')
            && $mail->hasTo('ops@example.com')
            && count($mail->to ?? []) === 2;
    });

    Mail::assertQueued(AdminUsageDigestMail::class, function (AdminUsageDigestMail $mail): bool {
        return $mail->hasTo('admin@example.com')
            && $mail->hasTo('ops@example.com')
            && count($mail->to ?? []) === 2
            && array_key_exists('top_storage_users', $mail->payload)
            && array_key_exists('top_ai_users', $mail->payload)
            && array_key_exists('top_bandwidth_users', $mail->payload)
            && array_key_exists('queue_health', $mail->payload)
            && array_key_exists('margin_risk_summary', $mail->payload);
    });
});
