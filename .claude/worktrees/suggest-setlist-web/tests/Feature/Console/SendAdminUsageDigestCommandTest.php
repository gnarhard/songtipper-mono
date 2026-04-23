<?php

declare(strict_types=1);

use App\Services\AccountUsageService;

it('calls sendWeeklyDigest and reports success', function () {
    $service = mock(AccountUsageService::class);
    $service->shouldReceive('sendWeeklyDigest')->once();

    app()->instance(AccountUsageService::class, $service);

    $this->artisan('account-usage:send-admin-digest')
        ->expectsOutput('Admin usage digest queued.')
        ->assertExitCode(0);
});
