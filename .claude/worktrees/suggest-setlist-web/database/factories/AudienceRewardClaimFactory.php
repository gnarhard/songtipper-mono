<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AudienceProfile;
use App\Models\AudienceRewardClaim;
use App\Models\RewardThreshold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudienceRewardClaim>
 */
class AudienceRewardClaimFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audience_profile_id' => AudienceProfile::factory(),
            'reward_threshold_id' => RewardThreshold::factory(),
            'claimed_at' => now(),
        ];
    }

    /**
     * Pending = earned by the audience member but not yet handed over by the
     * performer.
     */
    public function pending(): static
    {
        return $this->state(fn () => ['claimed_at' => null]);
    }
}
