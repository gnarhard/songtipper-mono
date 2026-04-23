<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string LEGACY_UNIQUE = 'charts_unique_song_project_owner';

    private const string VERSIONED_UNIQUE = 'charts_unique_owner_project_song';

    public function up(): void
    {
        if ($this->hasIndex('charts', self::LEGACY_UNIQUE)) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->dropUnique(self::LEGACY_UNIQUE);
            });
        }

        if (! $this->hasIndex('charts', self::VERSIONED_UNIQUE)) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->unique(['owner_user_id', 'project_song_id'], self::VERSIONED_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('charts', self::VERSIONED_UNIQUE)) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->dropUnique(self::VERSIONED_UNIQUE);
            });
        }

        if (! $this->hasIndex('charts', self::LEGACY_UNIQUE)) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->unique(['owner_user_id', 'song_id', 'project_id'], self::LEGACY_UNIQUE);
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(static fn (array $index): bool => $index['name'] === $indexName);
    }
};
