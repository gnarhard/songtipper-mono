<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'chart_annotation_versions';

    private const string LEGACY_UNIQUE_INDEX = 'chart_annotations_unique_local_version';

    private const string SINGLE_STATE_UNIQUE_INDEX = 'chart_annotations_unique_page';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table(self::TABLE)->delete();

        if ($this->hasIndex(self::LEGACY_UNIQUE_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::LEGACY_UNIQUE_INDEX);
            });
        }

        if (Schema::hasColumn(self::TABLE, 'base_version_id')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropColumn('base_version_id');
            });
        }

        if (! $this->hasIndex(self::SINGLE_STATE_UNIQUE_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(
                    ['chart_id', 'owner_user_id', 'page_number'],
                    self::SINGLE_STATE_UNIQUE_INDEX
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table(self::TABLE)->delete();

        if ($this->hasIndex(self::SINGLE_STATE_UNIQUE_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::SINGLE_STATE_UNIQUE_INDEX);
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'base_version_id')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->string('base_version_id')->nullable()->after('local_version_id');
            });
        }

        if (! $this->hasIndex(self::LEGACY_UNIQUE_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(
                    ['chart_id', 'owner_user_id', 'page_number', 'local_version_id'],
                    self::LEGACY_UNIQUE_INDEX
                );
            });
        }
    }

    private function hasIndex(string $indexName): bool
    {
        return collect(Schema::getIndexes(self::TABLE))
            ->contains(static fn (array $index): bool => $index['name'] === $indexName);
    }
};
