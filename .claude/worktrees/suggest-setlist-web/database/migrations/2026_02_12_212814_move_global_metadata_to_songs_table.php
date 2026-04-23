<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->string('energy_level')->nullable()->after('normalized_key');
            $table->string('era', 50)->nullable()->after('energy_level');
            $table->string('genre', 50)->nullable()->after('era');
            $table->string('original_musical_key', 8)->nullable()->after('genre');
            $table->unsignedInteger('duration_in_seconds')->nullable()->after('original_musical_key');
        });

        // Backfill from project_songs: take the first non-null value per song_id.
        $fields = ['energy_level', 'era', 'genre', 'original_musical_key', 'duration_in_seconds'];

        foreach ($fields as $field) {
            DB::statement("
                UPDATE songs
                SET {$field} = (
                    SELECT ps.{$field}
                    FROM project_songs ps
                    WHERE ps.song_id = songs.id
                      AND ps.{$field} IS NOT NULL
                    ORDER BY ps.id ASC
                    LIMIT 1
                )
                WHERE songs.{$field} IS NULL
            ");
        }

        Schema::table('project_songs', function (Blueprint $table) {
            $table->dropColumn(['energy_level', 'era', 'genre', 'original_musical_key', 'duration_in_seconds']);
        });
    }

    public function down(): void
    {
        Schema::table('project_songs', function (Blueprint $table) {
            $table->string('energy_level')->nullable()->after('song_id');
            $table->string('era', 50)->nullable()->after('energy_level');
            $table->string('genre', 50)->nullable()->after('era');
            $table->string('original_musical_key', 8)->nullable()->after('performed_musical_key');
            $table->unsignedInteger('duration_in_seconds')->nullable()->after('tuning');
        });

        // Backfill project_songs from songs.
        $fields = ['energy_level', 'era', 'genre', 'original_musical_key', 'duration_in_seconds'];

        foreach ($fields as $field) {
            DB::statement("
                UPDATE project_songs
                SET {$field} = (
                    SELECT s.{$field}
                    FROM songs s
                    WHERE s.id = project_songs.song_id
                )
            ");
        }

        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn(['energy_level', 'era', 'genre', 'original_musical_key', 'duration_in_seconds']);
        });
    }
};
