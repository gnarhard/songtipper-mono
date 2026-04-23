<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CashTip;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashTip>
 */
class CashTipFactory extends Factory
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
            'amount_cents' => fake()->numberBetween(500, 10000),
            'local_date' => now()->toDateString(),
            'timezone' => 'America/Denver',
            'note' => null,
        ];
    }

    public function withNote(string $note = 'Cash from gig'): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => $note,
        ]);
    }

    public function forDate(string $localDate): static
    {
        return $this->state(fn (array $attributes) => [
            'local_date' => $localDate,
        ]);
    }
}
