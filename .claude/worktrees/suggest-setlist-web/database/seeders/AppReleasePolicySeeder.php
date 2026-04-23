<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AppReleasePolicy;
use Illuminate\Database\Seeder;

class AppReleasePolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (AppReleasePolicy::platforms() as $platform) {
            AppReleasePolicy::query()->updateOrCreate(
                ['platform' => $platform],
                [
                    'latest_version' => '0.0.0',
                    'latest_build_number' => 0,
                    'store_url' => null,
                    'archive_url' => null,
                    'is_enabled' => false,
                ]
            );
        }
    }
}
