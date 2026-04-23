<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AccountUsageAiOperationKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountUsageAiOperationKey>
 */
class AccountUsageAiOperationKeyFactory extends Factory
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
            'operation_key' => $this->faker->uuid(),
            'provider' => 'openai',
            'category' => 'interactive_metadata',
            'happened_at' => now(),
        ];
    }
}
