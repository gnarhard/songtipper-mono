<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AudienceProfile;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AudienceProfile>
 */
class AudienceProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'visitor_token' => Str::lower((string) Str::uuid()),
            'display_name' => fake()->randomElement(['Curious Fox', 'Bright Otter', 'Happy Robin']),
            'last_seen_ip' => fake()->ipv4(),
            'last_seen_at' => now(),
        ];
    }
}
