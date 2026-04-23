<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AccountUsageFlag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountUsageFlag>
 */
class AccountUsageFlagFactory extends Factory
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
            'type' => 'ai_spike',
            'severity' => 'review',
            'status' => 'open',
            'summary' => $this->faker->sentence(),
            'context' => [],
            'auto_blocked' => false,
            'opened_at' => now(),
            'reviewed_at' => null,
            'resolved_at' => null,
        ];
    }
}
