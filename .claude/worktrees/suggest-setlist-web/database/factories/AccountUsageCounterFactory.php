<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AccountUsageCounter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountUsageCounter>
 */
class AccountUsageCounterFactory extends Factory
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
            'storage_bytes' => 0,
            'chart_pdf_bytes' => 0,
            'chart_render_bytes' => 0,
            'performer_image_bytes' => 0,
            'lifetime_ai_operations' => 0,
            'lifetime_estimated_ai_cost_micros' => 0,
            'lifetime_estimated_bandwidth_bytes' => 0,
            'last_activity_at' => now(),
            'warning_markers' => [],
            'review_state' => 'clear',
            'review_reason' => null,
            'blocked_at' => null,
            'inactivity_warning_sent_at' => null,
            'archived_render_images_at' => null,
        ];
    }
}
