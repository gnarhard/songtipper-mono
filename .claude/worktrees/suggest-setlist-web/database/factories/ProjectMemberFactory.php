<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMember>
 */
class ProjectMemberFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => ProjectMemberRole::Member,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ProjectMemberRole::Owner,
        ]);
    }

    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ProjectMemberRole::Member,
        ]);
    }

    public function readonly(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ProjectMemberRole::Readonly,
        ]);
    }
}
