<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProjectSong;
use App\Models\SetlistSet;
use App\Models\SetlistSong;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SetlistSong>
 */
class SetlistSongFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'set_id' => SetlistSet::factory(),
            'project_song_id' => ProjectSong::factory(),
            'order_index' => 0,
            'notes' => null,
            'color_hex' => null,
        ];
    }
}
