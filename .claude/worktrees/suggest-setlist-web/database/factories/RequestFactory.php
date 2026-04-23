<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Project;
use App\Models\Request;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Request>
 */
class RequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipAmount = fake()->randomElement([500, 1000, 2000, 5000]);

        return [
            'project_id' => Project::factory(),
            'audience_profile_id' => null,
            'song_id' => Song::factory(),
            'tip_amount_cents' => $tipAmount,
            'score_cents' => $tipAmount,
            'status' => RequestStatus::Active,
            'payment_provider' => 'stripe',
            'payment_intent_id' => 'pi_'.fake()->uuid(),
            'requested_from_ip' => fake()->ipv4(),
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestStatus::Active,
        ]);
    }

    public function played(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestStatus::Played,
            'played_at' => now(),
        ]);
    }

    public function withTip(int $cents): static
    {
        return $this->state(fn (array $attributes) => [
            'tip_amount_cents' => $cents,
            'score_cents' => $cents,
        ]);
    }

    public function fromIp(string $ip): static
    {
        return $this->state(fn (array $attributes) => [
            'requested_from_ip' => $ip,
        ]);
    }
}
