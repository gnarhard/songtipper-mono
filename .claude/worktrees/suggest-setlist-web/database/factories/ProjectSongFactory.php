<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectSong>
 */
class ProjectSongFactory extends Factory
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
            'song_id' => Song::factory(),
            'instrumental' => false,
            'learned' => true,
            'theme' => null,
            'notes' => null,
        ];
    }
}
