<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setlist;
use App\Models\SetlistShareAcceptance;
use App\Models\SetlistShareLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SetlistShareAcceptance>
 */
class SetlistShareAcceptanceFactory extends Factory
{
    protected $model = SetlistShareAcceptance::class;

    public function definition(): array
    {
        return [
            'setlist_share_link_id' => SetlistShareLink::factory(),
            'user_id' => User::factory(),
            'copied_setlist_id' => Setlist::factory(),
        ];
    }
}
