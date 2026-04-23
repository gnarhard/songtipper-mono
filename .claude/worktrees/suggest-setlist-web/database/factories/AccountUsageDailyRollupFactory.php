<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AccountUsageDailyRollup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountUsageDailyRollup>
 */
class AccountUsageDailyRollupFactory extends Factory
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
            'rollup_date' => now()->toDateString(),
            'storage_delta_bytes' => 0,
            'storage_bytes_snapshot' => 0,
            'ai_operations' => 0,
            'bulk_ai_operations' => 0,
            'estimated_ai_cost_micros' => 0,
            'estimated_bandwidth_bytes' => 0,
            'queue_failures' => 0,
            'render_failures' => 0,
            'limit_rejections' => 0,
        ];
    }
}
