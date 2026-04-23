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
        if (
            Schema::hasColumn('songs', 'mood') &&
            ! Schema::hasColumn('songs', 'theme')
        ) {
            Schema::table('songs', function (Blueprint $table): void {
                $table->string('theme', 64)->nullable()->after('genre')->index();
            });

            DB::statement('UPDATE songs SET theme = mood WHERE theme IS NULL AND mood IS NOT NULL');

            Schema::table('songs', function (Blueprint $table): void {
                $table->dropIndex(['mood']);
                $table->dropColumn('mood');
            });
        }

        if (
            Schema::hasColumn('project_songs', 'mood') &&
            ! Schema::hasColumn('project_songs', 'theme')
        ) {
            Schema::table('project_songs', function (Blueprint $table): void {
                $table->string('theme', 64)->nullable()->after('genre')->index();
            });

            DB::statement('UPDATE project_songs SET theme = mood WHERE theme IS NULL AND mood IS NOT NULL');

            Schema::table('project_songs', function (Blueprint $table): void {
                $table->dropIndex(['mood']);
                $table->dropColumn('mood');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('songs', 'theme') &&
            ! Schema::hasColumn('songs', 'mood')
        ) {
            Schema::table('songs', function (Blueprint $table): void {
                $table->string('mood', 64)->nullable()->after('genre')->index();
            });

            DB::statement('UPDATE songs SET mood = theme WHERE mood IS NULL AND theme IS NOT NULL');

            Schema::table('songs', function (Blueprint $table): void {
                $table->dropIndex(['theme']);
                $table->dropColumn('theme');
            });
        }

        if (
            Schema::hasColumn('project_songs', 'theme') &&
            ! Schema::hasColumn('project_songs', 'mood')
        ) {
            Schema::table('project_songs', function (Blueprint $table): void {
                $table->string('mood', 64)->nullable()->after('genre')->index();
            });

            DB::statement('UPDATE project_songs SET mood = theme WHERE mood IS NULL AND theme IS NOT NULL');

            Schema::table('project_songs', function (Blueprint $table): void {
                $table->dropIndex(['theme']);
                $table->dropColumn('theme');
            });
        }
    }
};
