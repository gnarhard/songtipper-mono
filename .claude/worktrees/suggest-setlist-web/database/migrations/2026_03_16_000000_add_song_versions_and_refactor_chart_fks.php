<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string PROJECT_SONGS_LEGACY_UNIQUE = 'project_songs_project_id_song_id_unique';

    private const string PROJECT_SONGS_VERSIONED_UNIQUE = 'project_songs_project_id_song_id_version_label_unique';

    private const string CHARTS_PROJECT_SONG_INDEX = 'charts_project_song_index';

    private const string CHARTS_PROJECT_SONGS_FK = 'charts_project_song_fk';

    private const string CHARTS_PROJECT_SONG_ID_FK = 'charts_project_song_id_foreign';

    private const string PROJECT_SONG_CHARTS_LEGACY_UNIQUE = 'project_song_charts_project_id_song_id_chart_id_unique';

    private const string PROJECT_SONG_CHARTS_NEW_UNIQUE = 'project_song_charts_project_song_id_chart_id_unique';

    private const string PROJECT_SONG_CHARTS_PROJECT_ID_INDEX = 'project_song_charts_project_id_index';

    private const string PROJECT_SONG_CHARTS_PROJECT_SONG_ID_FK = 'project_song_charts_project_song_id_foreign';

    public function up(): void
    {
        if (! Schema::hasColumn('project_songs', 'version_label')) {
            Schema::table('project_songs', function (Blueprint $table): void {
                $table->string('version_label', 50)->default('')->after('song_id');
            });
        }

        if (! Schema::hasColumn('charts', 'project_song_id')) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_song_id')->nullable()->after('project_id');
            });
        }

        DB::statement('
            UPDATE charts
            SET project_song_id = (
                SELECT ps.id
                FROM project_songs ps
                WHERE ps.project_id = charts.project_id
                  AND ps.song_id = charts.song_id
                LIMIT 1
            )
            WHERE charts.project_id IS NOT NULL
              AND charts.song_id IS NOT NULL
        ');

        $chartsCompositeFk = $this->findForeignKeyByColumns('charts', ['project_id', 'song_id']);

        if ($chartsCompositeFk !== null) {
            Schema::table('charts', function (Blueprint $table) use ($chartsCompositeFk): void {
                $table->dropForeign($chartsCompositeFk);
            });
        } elseif ($this->hasForeignKeyByColumns('charts', ['project_id', 'song_id'])) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->dropForeign(['project_id', 'song_id']);
            });
        }

        if ($this->hasIndex('charts', self::CHARTS_PROJECT_SONG_INDEX)) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->dropIndex(self::CHARTS_PROJECT_SONG_INDEX);
            });
        }

        if (! $this->hasIndex('project_songs', self::PROJECT_SONGS_VERSIONED_UNIQUE)) {
            Schema::table('project_songs', function (Blueprint $table): void {
                $table->unique(['project_id', 'song_id', 'version_label']);
            });
        }

        if ($this->hasIndex('project_songs', self::PROJECT_SONGS_LEGACY_UNIQUE)) {
            Schema::table('project_songs', function (Blueprint $table): void {
                $table->dropUnique(self::PROJECT_SONGS_LEGACY_UNIQUE);
            });
        }

        if (! $this->hasForeignKey('charts', self::CHARTS_PROJECT_SONG_ID_FK)) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->foreign('project_song_id')
                    ->references('id')
                    ->on('project_songs')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('project_song_charts', 'project_song_id')) {
            Schema::table('project_song_charts', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_song_id')->nullable()->after('id');
            });
        }

        DB::statement('
            UPDATE project_song_charts
            SET project_song_id = (
                SELECT ps.id
                FROM project_songs ps
                WHERE ps.project_id = project_song_charts.project_id
                  AND ps.song_id = project_song_charts.song_id
                LIMIT 1
            )
        ');

        if (! $this->hasIndex('project_song_charts', self::PROJECT_SONG_CHARTS_PROJECT_ID_INDEX)) {
            Schema::table('project_song_charts', function (Blueprint $table): void {
                $table->index(['project_id'], self::PROJECT_SONG_CHARTS_PROJECT_ID_INDEX);
            });
        }

        if ($this->hasIndex('project_song_charts', self::PROJECT_SONG_CHARTS_LEGACY_UNIQUE)) {
            Schema::table('project_song_charts', function (Blueprint $table): void {
                $table->dropUnique(self::PROJECT_SONG_CHARTS_LEGACY_UNIQUE);
            });
        }

        if (! $this->hasIndex('project_song_charts', self::PROJECT_SONG_CHARTS_NEW_UNIQUE)) {
            Schema::table('project_song_charts', function (Blueprint $table): void {
                $table->unique(['project_song_id', 'chart_id']);
            });
        }

        if (! $this->hasForeignKey('project_song_charts', self::PROJECT_SONG_CHARTS_PROJECT_SONG_ID_FK)) {
            Schema::table('project_song_charts', function (Blueprint $table): void {
                $table->foreign('project_song_id')
                    ->references('id')
                    ->on('project_songs')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if ($this->hasForeignKey('project_song_charts', self::PROJECT_SONG_CHARTS_PROJECT_SONG_ID_FK)) {
            Schema::table('project_song_charts', function (Blueprint $table): void {
                $table->dropForeign(self::PROJECT_SONG_CHARTS_PROJECT_SONG_ID_FK);
            });
        }

        if ($this->hasIndex('project_song_charts', self::PROJECT_SONG_CHARTS_NEW_UNIQUE)) {
            Schema::table('project_song_charts', function (Blueprint $table): void {
                $table->dropUnique(self::PROJECT_SONG_CHARTS_NEW_UNIQUE);
            });
        }

        if (! $this->hasIndex('project_song_charts', self::PROJECT_SONG_CHARTS_LEGACY_UNIQUE)) {
            Schema::table('project_song_charts', function (Blueprint $table): void {
                $table->unique(['project_id', 'song_id', 'chart_id']);
            });
        }

        if (Schema::hasColumn('project_song_charts', 'project_song_id')) {
            Schema::table('project_song_charts', function (Blueprint $table): void {
                $table->dropColumn('project_song_id');
            });
        }

        if ($this->hasIndex('project_song_charts', self::PROJECT_SONG_CHARTS_PROJECT_ID_INDEX)) {
            Schema::table('project_song_charts', function (Blueprint $table): void {
                $table->dropIndex(self::PROJECT_SONG_CHARTS_PROJECT_ID_INDEX);
            });
        }

        if ($this->hasForeignKey('charts', self::CHARTS_PROJECT_SONG_ID_FK)) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->dropForeign(self::CHARTS_PROJECT_SONG_ID_FK);
            });
        }

        if (! $this->hasIndex('charts', self::CHARTS_PROJECT_SONG_INDEX)) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->index(['project_id', 'song_id'], self::CHARTS_PROJECT_SONG_INDEX);
            });
        }

        if (! $this->hasIndex('project_songs', self::PROJECT_SONGS_LEGACY_UNIQUE)) {
            Schema::table('project_songs', function (Blueprint $table): void {
                $table->unique(['project_id', 'song_id']);
            });
        }

        if ($this->hasIndex('project_songs', self::PROJECT_SONGS_VERSIONED_UNIQUE)) {
            Schema::table('project_songs', function (Blueprint $table): void {
                $table->dropUnique(self::PROJECT_SONGS_VERSIONED_UNIQUE);
            });
        }

        if (! $this->hasForeignKey('charts', self::CHARTS_PROJECT_SONGS_FK)) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->foreign(['project_id', 'song_id'], self::CHARTS_PROJECT_SONGS_FK)
                    ->references(['project_id', 'song_id'])
                    ->on('project_songs')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('charts', 'project_song_id')) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->dropColumn('project_song_id');
            });
        }

        if (Schema::hasColumn('project_songs', 'version_label')) {
            Schema::table('project_songs', function (Blueprint $table): void {
                $table->dropColumn('version_label');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(static fn (array $index): bool => $index['name'] === $indexName);
    }

    private function hasForeignKey(string $table, string $foreignKeyName): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(static fn (array $foreignKey): bool => $foreignKey['name'] === $foreignKeyName);
    }

    private function findForeignKeyByColumns(string $table, array $columns): ?string
    {
        $sortedColumns = collect($columns)->sort()->values()->all();

        $foreignKey = collect(Schema::getForeignKeys($table))
            ->first(static function (array $key) use ($sortedColumns): bool {
                $keyColumns = collect($key['columns'] ?? [])->sort()->values()->all();

                return $keyColumns === $sortedColumns;
            });

        if (! is_array($foreignKey)) {
            return null;
        }

        return $foreignKey['name'] ?? null;
    }

    private function hasForeignKeyByColumns(string $table, array $columns): bool
    {
        $sortedColumns = collect($columns)->sort()->values()->all();

        return collect(Schema::getForeignKeys($table))
            ->contains(static function (array $key) use ($sortedColumns): bool {
                $keyColumns = collect($key['columns'] ?? [])->sort()->values()->all();

                return $keyColumns === $sortedColumns;
            });
    }
};
