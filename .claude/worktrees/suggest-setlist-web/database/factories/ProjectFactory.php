<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'owner_user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'performer_info_url' => null,
            'performer_profile_image_path' => null,
            'min_tip_cents' => 500,
            'quick_tip_1_cents' => 2000,
            'quick_tip_2_cents' => 1500,
            'quick_tip_3_cents' => 1000,
            'is_accepting_requests' => true,
            'is_accepting_tips' => true,
            'is_accepting_original_requests' => true,
            'show_persistent_queue_strip' => true,
        ];
    }

    public function notAcceptingRequests(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_accepting_requests' => false,
        ]);
    }

    public function withMinTip(int $cents): static
    {
        return $this->state(fn (array $attributes) => [
            'min_tip_cents' => $cents,
        ]);
    }

    public function notAcceptingTips(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_accepting_tips' => false,
        ]);
    }
}
