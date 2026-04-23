<?php

declare(strict_types=1);

use App\Services\AccountUsageService;

it('calls monitorAccounts and reports success', function () {
    $service = mock(AccountUsageService::class);
    $service->shouldReceive('monitorAccounts')->once();

    app()->instance(AccountUsageService::class, $service);

    $this->artisan('account-usage:monitor')
        ->expectsOutput('Account usage monitoring completed.')
        ->assertExitCode(0);
});
