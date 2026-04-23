<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\RewardThreshold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardThreshold>
 */
class RewardThresholdFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'threshold_cents' => RewardThreshold::DEFAULT_FREE_REQUEST_THRESHOLD_CENTS,
            'reward_type' => RewardThreshold::TYPE_FREE_REQUEST,
            'reward_label' => RewardThreshold::DEFAULT_FREE_REQUEST_LABEL,
            'reward_icon' => RewardThreshold::DEFAULT_FREE_REQUEST_ICON,
            'reward_description' => null,
            'is_repeating' => true,
            'sort_order' => 0,
        ];
    }

    public function nonRepeating(): static
    {
        return $this->state(['is_repeating' => false]);
    }

    public function withThreshold(int $cents): static
    {
        return $this->state(['threshold_cents' => $cents]);
    }

    public function withType(string $type, string $label): static
    {
        return $this->state([
            'reward_type' => $type,
            'reward_label' => $label,
        ]);
    }
}
