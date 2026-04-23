<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapses project_learning_songs into the repertoire.
 *
 * Each learning song becomes a ProjectSong with learned=false,
 * owned by the project owner (members pick it up via the normal
 * member sync flows). YouTube / Ultimate Guitar URLs are hoisted
 * onto the global Song record so every project sharing that
 * canonical song sees the same links.
 *
 * Notes:
 * - Irreversible: down() recreates the empty table structure but
 *   cannot restore the learning-song rows themselves.
 * - Does NOT dispatch FanOutSongToMembers. Fan-out would race
 *   against the migration and pollute queues. Members can still
 *   use member repertoire sync to pick up the new rows later.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_learning_songs')) {
            return;
        }

        DB::table('project_learning_songs')
            ->orderBy('id')
            ->chunkById(500, function ($learningSongs): void {
                DB::transaction(function () use ($learningSongs): void {
                    foreach ($learningSongs as $learningSong) {
                        $this->migrateOne($learningSong);
                    }
                });
            });

        DB::table('project_learning_songs')->delete();

        Schema::dropIfExists('project_learning_songs');
    }

    public function down(): void
    {
        if (Schema::hasTable('project_learning_songs')) {
            return;
        }

        Schema::create('project_learning_songs', function ($table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->string('youtube_video_url', 2048)->nullable();
            $table->string('ultimate_guitar_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'song_id']);
            $table->index(['project_id', 'created_at']);
        });
    }

    private function migrateOne(object $learningSong): void
    {
        $song = DB::table('songs')->where('id', $learningSong->song_id)->first();
        if ($song === null) {
            return;
        }

        $project = DB::table('projects')->where('id', $learningSong->project_id)->first();
        if ($project === null) {
            return;
        }

        $songUpdates = [];
        if (($song->youtube_video_url ?? null) === null && ! empty($learningSong->youtube_video_url)) {
            $songUpdates['youtube_video_url'] = $learningSong->youtube_video_url;
        }
        if (($song->ultimate_guitar_url ?? null) === null && ! empty($learningSong->ultimate_guitar_url)) {
            $songUpdates['ultimate_guitar_url'] = $learningSong->ultimate_guitar_url;
        }
        if ($songUpdates !== []) {
            DB::table('songs')
                ->where('id', $song->id)
                ->update($songUpdates);
        }

        $existing = DB::table('project_songs')
            ->where('project_id', $learningSong->project_id)
            ->where('user_id', $project->owner_user_id)
            ->where('song_id', $learningSong->song_id)
            ->where('version_label', '')
            ->first();

        if ($existing !== null) {
            // Already in repertoire — just flip the flag.
            DB::table('project_songs')
                ->where('id', $existing->id)
                ->update(['learned' => false, 'updated_at' => now()]);

            return;
        }

        $now = now();
        DB::table('project_songs')->insert([
            'project_id' => $learningSong->project_id,
            'user_id' => $project->owner_user_id,
            'song_id' => $learningSong->song_id,
            'title' => $song->title,
            'artist' => $song->artist,
            'version_label' => '',
            'notes' => $learningSong->notes,
            'instrumental' => false,
            'mashup' => false,
            'is_public' => true,
            'learned' => false,
            'performance_count' => 0,
            'created_at' => $learningSong->created_at ?? $now,
            'updated_at' => $now,
        ]);
    }
};
