<?php

declare(strict_types=1);

use App\Jobs\EvaluateEarningsThreshold;
use App\Models\User;
use App\Services\EarningsThresholdService;
use Mockery\MockInterface;

it('calls the earnings threshold service with the user', function () {
    $user = User::factory()->create();

    $this->mock(EarningsThresholdService::class, function (MockInterface $mock) use ($user) {
        $mock->shouldReceive('evaluate')
            ->once()
            ->withArgs(fn (User $passedUser) => $passedUser->id === $user->id);
    });

    (new EvaluateEarningsThreshold($user->id))->handle(
        app(EarningsThresholdService::class)
    );
});

it('does nothing when the user does not exist', function () {
    $this->mock(EarningsThresholdService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('evaluate');
    });

    (new EvaluateEarningsThreshold(999999))->handle(
        app(EarningsThresholdService::class)
    );
});
