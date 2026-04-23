<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AppReleasePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppReleasePolicy>
 */
class AppReleasePolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platform = AppReleasePolicy::PLATFORM_IOS;

        return [
            'platform' => $platform,
            'latest_version' => '1.0.0',
            'latest_build_number' => 1,
            'store_url' => 'https://example.com/ios',
            'archive_url' => null,
            'is_enabled' => true,
        ];
    }

    public function forPlatform(string $platform): static
    {
        return $this->state(function () use ($platform): array {
            return [
                'platform' => $platform,
                'store_url' => AppReleasePolicy::isMobilePlatform($platform)
                    ? "https://example.com/{$platform}"
                    : null,
                'archive_url' => AppReleasePolicy::isDesktopPlatform($platform)
                    ? "https://downloads.example.com/{$platform}/app-archive.json"
                    : null,
            ];
        });
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => [
            'is_enabled' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'is_enabled' => false,
        ]);
    }
}
