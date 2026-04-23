<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
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
        // Remove project-scoped charts that do not map to a repertoire song.
        DB::table('charts')
            ->whereNotNull('project_id')
            ->whereNotExists(function (Builder $query): void {
                $query->select(DB::raw(1))
                    ->from('project_songs')
                    ->whereColumn('project_songs.project_id', 'charts.project_id')
                    ->whereColumn('project_songs.song_id', 'charts.song_id');
            })
            ->delete();

        Schema::table('charts', function (Blueprint $table) {
            $table->index(['project_id', 'song_id'], 'charts_project_song_index');

            $table->foreign(['project_id', 'song_id'], 'charts_project_song_fk')
                ->references(['project_id', 'song_id'])
                ->on('project_songs')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charts', function (Blueprint $table) {
            $table->dropForeign('charts_project_song_fk');
            $table->dropIndex('charts_project_song_index');
        });
    }
};
