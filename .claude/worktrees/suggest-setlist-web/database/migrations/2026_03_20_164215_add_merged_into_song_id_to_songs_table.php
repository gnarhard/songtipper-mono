<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->foreignId('merged_into_song_id')
                ->nullable()
                ->after('normalized_key')
                ->constrained('songs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_song_id');
        });
    }
};
