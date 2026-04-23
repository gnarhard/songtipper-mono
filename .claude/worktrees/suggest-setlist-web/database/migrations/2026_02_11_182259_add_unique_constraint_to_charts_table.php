<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // Remove duplicate charts, keeping only the most recent one for each combination.
            DB::statement('
                DELETE c1 FROM charts c1
                INNER JOIN charts c2
                WHERE c1.owner_user_id = c2.owner_user_id
                AND c1.song_id = c2.song_id
                AND c1.project_id = c2.project_id
                AND c1.id < c2.id
            ');
        }

        Schema::table('charts', function (Blueprint $table) {
            // Add unique constraint to prevent duplicate charts per owner/song/project.
            $table->unique(['owner_user_id', 'song_id', 'project_id'], 'charts_unique_song_project_owner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charts', function (Blueprint $table) {
            $table->dropUnique('charts_unique_song_project_owner');
        });
    }
};
