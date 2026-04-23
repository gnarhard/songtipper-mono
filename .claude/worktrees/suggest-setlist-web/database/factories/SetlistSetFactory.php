<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setlist;
use App\Models\SetlistSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SetlistSet>
 */
class SetlistSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'setlist_id' => Setlist::factory(),
            'name' => 'Set '.fake()->numberBetween(1, 5),
            'order_index' => 0,
        ];
    }
}
