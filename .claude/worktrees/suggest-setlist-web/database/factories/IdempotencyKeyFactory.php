<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'audience_profile_id' => null,
            'actor_key' => 'u:'.fake()->unique()->numberBetween(1000, 999999),
            'method' => fake()->randomElement(['POST', 'PUT', 'PATCH', 'DELETE']),
            'path' => '/api/v1/test/path',
            'idempotency_key' => Str::uuid()->toString(),
            'status_code' => 200,
            'response_json' => ['message' => 'ok'],
        ];
    }
}
