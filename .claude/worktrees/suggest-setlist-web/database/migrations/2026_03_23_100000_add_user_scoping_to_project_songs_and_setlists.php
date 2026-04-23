<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string PS_OLD_UNIQ = 'project_songs_project_id_song_id_version_label_unique';

    private const string PS_NEW_UNIQ = 'ps_proj_user_song_ver_uniq';

    public function up(): void
    {
        // --- project_songs ---
        Schema::table('project_songs', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->after('project_id');
            $table->unsignedBigInteger('source_project_song_id')->nullable()->after('song_id');

            $table->foreign('source_project_song_id', 'ps_source_ps_fk')
                ->references('id')->on('project_songs')->nullOnDelete();
        });

        // Backfill: set user_id = project.owner_user_id for all existing rows.
        DB::statement(<<<'SQL'
            UPDATE project_songs
            SET user_id = (SELECT owner_user_id FROM projects WHERE projects.id = project_songs.project_id)
            WHERE user_id IS NULL
        SQL);

        // Make user_id NOT NULL and add FK with cascade.
        Schema::table('project_songs', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Add new unique first (covers project_id for FK), then drop old.
        Schema::table('project_songs', function (Blueprint $table): void {
            $table->unique(
                ['project_id', 'user_id', 'song_id', 'version_label'],
                self::PS_NEW_UNIQ,
            );
            $table->index(['project_id', 'user_id'], 'ps_proj_user_idx');
        });

        Schema::table('project_songs', function (Blueprint $table): void {
            $table->dropUnique(self::PS_OLD_UNIQ);
        });

        // --- setlists ---
        Schema::table('setlists', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->after('project_id');

        });

        DB::statement(<<<'SQL'
            UPDATE setlists
            SET user_id = (SELECT owner_user_id FROM projects WHERE projects.id = setlists.project_id)
            WHERE user_id IS NULL
        SQL);

        Schema::table('setlists', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['project_id', 'user_id'], 'setlists_proj_user_idx');
        });
    }

    public function down(): void
    {
        Schema::table('setlists', function (Blueprint $table): void {
            $table->dropIndex('setlists_proj_user_idx');
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('project_songs', function (Blueprint $table): void {
            $table->dropUnique(self::PS_NEW_UNIQ);
            $table->dropIndex('ps_proj_user_idx');
            $table->dropForeign('ps_source_ps_fk');
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'source_project_song_id']);

            $table->unique(
                ['project_id', 'song_id', 'version_label'],
                self::PS_OLD_UNIQ,
            );
        });
    }
};
