<?php

declare(strict_types=1);

use App\Models\PerformanceSession;
use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\User;
use App\Services\PayoutWalletService;

it('returns project earnings summary', function () {
    $project = Project::factory()->create();

    // Create paid stripe request
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'payment_provider' => 'stripe',
        'tip_amount_cents' => 500,
        'status' => 'played',
    ]);

    // Create active queue request
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'payment_provider' => 'stripe',
        'tip_amount_cents' => 300,
        'status' => 'active',
    ]);

    // Create sessionless stripe request
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'payment_provider' => 'stripe',
        'tip_amount_cents' => 200,
        'performance_session_id' => null,
    ]);

    $service = new PayoutWalletService;
    $result = $service->projectEarningsSummary($project);

    expect($result)->toHaveKeys([
        'total_tip_amount_cents',
        'gross_tip_amount_cents',
        'fee_amount_cents',
        'net_tip_amount_cents',
        'paid_request_count',
        'active_queue_tip_amount_cents',
        'active_queue_request_count',
        'active_session_tip_amount_cents',
        'sessionless_tip_amount_cents',
    ])
        ->and($result['total_tip_amount_cents'])->toBeGreaterThan(0)
        ->and($result['paid_request_count'])->toBeGreaterThan(0);
});

it('returns gross, fees, and net lifetime earnings for a user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_user_id' => $user->id]);

    // Fully-settled stripe tip: $5.00 gross, $0.45 fee, $4.55 net
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'payment_provider' => 'stripe',
        'tip_amount_cents' => 500,
        'stripe_fee_amount_cents' => 45,
        'stripe_net_amount_cents' => 455,
    ]);

    // Another fully-settled stripe tip: $10.00 gross, $0.59 fee, $9.41 net
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'payment_provider' => 'stripe',
        'tip_amount_cents' => 1000,
        'stripe_fee_amount_cents' => 59,
        'stripe_net_amount_cents' => 941,
    ]);

    // Unsettled stripe tip (no fee info yet): $3.00 gross, fee unknown
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'payment_provider' => 'stripe',
        'tip_amount_cents' => 300,
        'stripe_fee_amount_cents' => null,
        'stripe_net_amount_cents' => null,
    ]);

    // Non-stripe request should be ignored entirely
    SongRequest::factory()->create([
        'project_id' => $project->id,
        'payment_provider' => 'none',
        'tip_amount_cents' => 2000,
    ]);

    $service = new PayoutWalletService;
    $result = $service->userLifetimeEarningsSummary($user);

    expect($result['gross_tip_amount_cents'])->toBe(1800)
        ->and($result['fee_amount_cents'])->toBe(104)
        ->and($result['net_tip_amount_cents'])->toBe(1696);
});

it('ignores stripe requests from other users in lifetime earnings', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownedProject = Project::factory()->create(['owner_user_id' => $user->id]);
    $otherProject = Project::factory()->create(['owner_user_id' => $otherUser->id]);

    SongRequest::factory()->create([
        'project_id' => $ownedProject->id,
        'payment_provider' => 'stripe',
        'tip_amount_cents' => 500,
        'stripe_fee_amount_cents' => 45,
        'stripe_net_amount_cents' => 455,
    ]);
    SongRequest::factory()->create([
        'project_id' => $otherProject->id,
        'payment_provider' => 'stripe',
        'tip_amount_cents' => 9999,
        'stripe_fee_amount_cents' => 999,
        'stripe_net_amount_cents' => 9000,
    ]);

    $service = new PayoutWalletService;
    $result = $service->userLifetimeEarningsSummary($user);

    expect($result['gross_tip_amount_cents'])->toBe(500)
        ->and($result['fee_amount_cents'])->toBe(45)
        ->and($result['net_tip_amount_cents'])->toBe(455);
});

it('includes active session earnings in summary', function () {
    $project = Project::factory()->create();
    $session = PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'started_at' => now(),
    ]);

    SongRequest::factory()->create([
        'project_id' => $project->id,
        'payment_provider' => 'stripe',
        'tip_amount_cents' => 700,
        'performance_session_id' => $session->id,
    ]);

    $service = new PayoutWalletService;
    $result = $service->projectEarningsSummary($project);

    expect($result['active_session_tip_amount_cents'])->toBe(700);
});

it('returns paginated project session earnings', function () {
    $project = Project::factory()->create();

    PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'started_at' => now()->subDay(),
    ]);
    PerformanceSession::factory()->create([
        'project_id' => $project->id,
        'started_at' => now(),
    ]);

    $service = new PayoutWalletService;
    $result = $service->projectSessionEarnings($project, 10);

    expect($result->total())->toBe(2)
        ->and($result->items())->toHaveCount(2);
});
