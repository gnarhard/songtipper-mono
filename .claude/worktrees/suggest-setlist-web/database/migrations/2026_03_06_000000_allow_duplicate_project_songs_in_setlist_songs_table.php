<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setlist_songs', function (Blueprint $table): void {
            $table->dropUnique('setlist_songs_set_id_project_song_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('setlist_songs', function (Blueprint $table): void {
            $table->unique(['set_id', 'project_song_id']);
        });
    }
};
