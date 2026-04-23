<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChartTheme;
use App\Models\Chart;
use App\Models\ChartRender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartRender>
 */
class ChartRenderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chart_id' => Chart::factory(),
            'page_number' => 1,
            'theme' => ChartTheme::Light,
            'storage_path_image' => 'charts/'.fake()->uuid().'/renders/light/page-1.png',
        ];
    }

    public function dark(): static
    {
        return $this->state(fn (array $attributes) => [
            'theme' => ChartTheme::Dark,
            'storage_path_image' => str_replace('/light/', '/dark/', $attributes['storage_path_image']),
        ]);
    }

    public function forPage(int $page): static
    {
        return $this->state(fn (array $attributes) => [
            'page_number' => $page,
            'storage_path_image' => preg_replace('/page-\d+/', "page-{$page}", $attributes['storage_path_image']),
        ]);
    }
}
