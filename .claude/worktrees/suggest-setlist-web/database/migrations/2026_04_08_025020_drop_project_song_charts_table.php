<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the project_song_charts junction table.
 *
 * The canonical chart-to-project_song link is Chart.project_song_id, which
 * has been the source of truth for the upload, AI lyric-sheet generator,
 * and copy flows for some time. The junction was only sporadically
 * populated and is no longer referenced anywhere in the application.
 *
 * Irreversible: down() recreates the empty table structure but cannot
 * restore the rows themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_song_charts');
    }

    public function down(): void
    {
        if (Schema::hasTable('project_song_charts')) {
            return;
        }

        Schema::create('project_song_charts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_song_id')->nullable();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chart_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_song_id', 'chart_id']);
            $table->index(['project_id'], 'project_song_charts_project_id_index');

            $table->foreign('project_song_id')
                ->references('id')
                ->on('project_songs')
                ->cascadeOnDelete();
        });
    }
};
