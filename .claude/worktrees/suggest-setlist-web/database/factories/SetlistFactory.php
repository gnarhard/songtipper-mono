<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Setlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setlist>
 */
class SetlistFactory extends Factory
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
            'user_id' => fn (array $attributes) => Project::find($attributes['project_id'])?->owner_user_id,
            'name' => fake()->words(2, true),
        ];
    }
}
