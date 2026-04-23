<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Chart;
use App\Models\Project;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chart>
 */
class ChartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'song_id' => Song::factory(),
            'project_id' => Project::factory(),
            'storage_disk' => 'r2',
            'storage_path_pdf' => 'charts/'.fake()->uuid().'/source.pdf',
            'source_sha256' => hash('sha256', fake()->uuid()),
            'original_filename' => fake()->words(2, true).'.pdf',
            'has_renders' => false,
            'page_count' => 0,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    public function withRenders(int $pageCount = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'has_renders' => true,
            'page_count' => $pageCount,
        ]);
    }
}
