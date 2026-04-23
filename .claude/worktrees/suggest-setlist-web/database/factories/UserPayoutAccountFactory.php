<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPayoutAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPayoutAccount>
 */
class UserPayoutAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'stripe_account_id' => 'acct_'.fake()->unique()->bothify('????????????????'),
            'country' => 'US',
            'default_currency' => 'usd',
            'details_submitted' => false,
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'requirements_currently_due' => ['external_account'],
            'requirements_past_due' => [],
            'requirements_disabled_reason' => null,
            'status' => UserPayoutAccount::STATUS_PENDING,
            'status_reason' => 'requirements_due',
            'last_synced_at' => now(),
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => [
            'details_submitted' => true,
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'requirements_currently_due' => [],
            'requirements_past_due' => [],
            'requirements_disabled_reason' => null,
            'status' => UserPayoutAccount::STATUS_ENABLED,
            'status_reason' => null,
        ]);
    }
}
