<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PerformanceSessionMode;
use App\Models\PerformanceSession;
use App\Models\Project;
use App\Models\Setlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceSession>
 */
class PerformanceSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'setlist_id' => Setlist::factory(),
            'mode' => fake()->randomElement(PerformanceSessionMode::cases()),
            'is_active' => true,
            'seed' => fake()->numberBetween(1, 99999999),
            'generation_version' => 'smart-v1',
            'started_at' => now(),
            'ended_at' => null,
        ];
    }
}
