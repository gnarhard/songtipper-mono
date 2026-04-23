<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Chart;
use App\Models\ChartPageUserPref;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartPageUserPref>
 */
class ChartPageUserPrefFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chart_id' => Chart::factory(),
            'owner_user_id' => User::factory(),
            'page_number' => 1,
            'zoom_scale' => 1.0,
            'offset_dx' => 0,
            'offset_dy' => 0,
        ];
    }
}
