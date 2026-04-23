<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Setlist;
use App\Models\SetlistShareLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SetlistShareLink>
 */
class SetlistShareLinkFactory extends Factory
{
    protected $model = SetlistShareLink::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'setlist_id' => Setlist::factory(),
            'created_by_user_id' => User::factory(),
            'token' => Str::random(48),
        ];
    }
}
