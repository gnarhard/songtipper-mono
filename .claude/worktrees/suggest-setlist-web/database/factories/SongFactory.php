<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EnergyLevel;
use App\Enums\SongTheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Song>
 */
class SongFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(rand(1, 4), true),
            'artist' => fake()->name(),
            'energy_level' => fake()->randomElement(EnergyLevel::cases()),
            'era' => fake()->randomElement(['60s', '70s', '80s', '90s', '2000s', '2010s', '2020s']),
            'genre' => fake()->randomElement(['Rock', 'Pop', 'Country', 'Jazz', 'Blues', 'R&B', 'Hip Hop']),
            'theme' => fake()->randomElement(SongTheme::values()),
        ];
    }

    public function lowEnergy(): static
    {
        return $this->state(fn (array $attributes) => [
            'energy_level' => EnergyLevel::Low,
        ]);
    }

    public function mediumEnergy(): static
    {
        return $this->state(fn (array $attributes) => [
            'energy_level' => EnergyLevel::Medium,
        ]);
    }

    public function highEnergy(): static
    {
        return $this->state(fn (array $attributes) => [
            'energy_level' => EnergyLevel::High,
        ]);
    }
}
