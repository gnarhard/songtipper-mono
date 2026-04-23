<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call(GraysonErhardProjectSongsSeeder::class);
        $this->call(TestAccountsSeeder::class);
        $this->call(AppReleasePolicySeeder::class);

        $this->command->info('Demo data seeded successfully!');
    }
}
