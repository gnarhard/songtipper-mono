<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdminDesignation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminDesignation>
 */
class AdminDesignationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
