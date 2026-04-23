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
        Schema::table('project_songs', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->after('song_id');
            $table->string('artist', 255)->nullable()->after('title');
        });

        DB::statement('
            UPDATE project_songs
            SET title = (SELECT title FROM songs WHERE songs.id = project_songs.song_id),
                artist = (SELECT artist FROM songs WHERE songs.id = project_songs.song_id)
        ');

        Schema::table('project_songs', function (Blueprint $table) {
            $table->string('title', 255)->nullable(false)->change();
            $table->string('artist', 255)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_songs', function (Blueprint $table) {
            $table->dropColumn(['title', 'artist']);
        });
    }
};
