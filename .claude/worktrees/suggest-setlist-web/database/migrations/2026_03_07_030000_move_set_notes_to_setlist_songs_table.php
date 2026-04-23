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
        Schema::table('setlist_songs', function (Blueprint $table): void {
            $table->dropForeign(['project_song_id']);
        });

        Schema::table('setlist_songs', function (Blueprint $table): void {
            $table->foreignId('project_song_id')->nullable()->change();
        });

        Schema::table('setlist_songs', function (Blueprint $table): void {
            $table->foreign('project_song_id')
                ->references('id')
                ->on('project_songs')
                ->cascadeOnDelete();
        });

        $setsWithNotes = DB::table('setlist_sets')
            ->select(['id', 'notes', 'notes_order_index'])
            ->whereNotNull('notes')
            ->orderBy('id')
            ->get();

        foreach ($setsWithNotes as $set) {
            $trimmedNotes = trim((string) $set->notes);
            if ($trimmedNotes === '') {
                continue;
            }

            $songCount = DB::table('setlist_songs')
                ->where('set_id', $set->id)
                ->count();
            $resolvedOrderIndex = min(
                max((int) ($set->notes_order_index ?? 0), 0),
                $songCount,
            );

            DB::table('setlist_songs')
                ->where('set_id', $set->id)
                ->where('order_index', '>=', $resolvedOrderIndex)
                ->increment('order_index');

            DB::table('setlist_songs')->insert([
                'set_id' => $set->id,
                'project_song_id' => null,
                'order_index' => $resolvedOrderIndex,
                'notes' => $trimmedNotes,
                'color_hex' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('setlist_sets', function (Blueprint $table): void {
            $table->dropColumn(['notes', 'notes_order_index']);
        });
    }

    public function down(): void
    {
        Schema::table('setlist_sets', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('name');
            $table->unsignedInteger('notes_order_index')
                ->nullable()
                ->after('notes');
        });

        $setIds = DB::table('setlist_sets')->pluck('id');

        foreach ($setIds as $setId) {
            $noteEntries = DB::table('setlist_songs')
                ->where('set_id', $setId)
                ->whereNull('project_song_id')
                ->orderBy('order_index')
                ->get(['id', 'notes', 'order_index']);

            if ($noteEntries->isNotEmpty()) {
                $combinedNotes = $noteEntries
                    ->pluck('notes')
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->implode("\n\n");

                DB::table('setlist_sets')
                    ->where('id', $setId)
                    ->update([
                        'notes' => $combinedNotes !== '' ? $combinedNotes : null,
                        'notes_order_index' => (int) $noteEntries->first()->order_index,
                    ]);

                DB::table('setlist_songs')
                    ->whereIn('id', $noteEntries->pluck('id'))
                    ->delete();

                $remainingEntries = DB::table('setlist_songs')
                    ->where('set_id', $setId)
                    ->orderBy('order_index')
                    ->orderBy('id')
                    ->get(['id']);

                foreach ($remainingEntries as $index => $entry) {
                    DB::table('setlist_songs')
                        ->where('id', $entry->id)
                        ->update(['order_index' => $index]);
                }
            }
        }

        Schema::table('setlist_songs', function (Blueprint $table): void {
            $table->dropForeign(['project_song_id']);
        });

        Schema::table('setlist_songs', function (Blueprint $table): void {
            $table->foreignId('project_song_id')->nullable(false)->change();
        });

        Schema::table('setlist_songs', function (Blueprint $table): void {
            $table->foreign('project_song_id')
                ->references('id')
                ->on('project_songs')
                ->cascadeOnDelete();
        });
    }
};
