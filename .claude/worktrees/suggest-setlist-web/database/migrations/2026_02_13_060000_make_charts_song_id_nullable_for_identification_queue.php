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
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $foreignKeys = DB::select(
            "
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'charts'
              AND COLUMN_NAME = 'song_id'
              AND REFERENCED_TABLE_NAME = 'songs'
            "
        );

        if ($foreignKeys !== []) {
            Schema::table('charts', function (Blueprint $table) use ($foreignKeys): void {
                $table->dropForeign($foreignKeys[0]->CONSTRAINT_NAME);
            });
        }

        Schema::table('charts', function (Blueprint $table): void {
            $table->unsignedBigInteger('song_id')->nullable()->change();

            $table->foreign('song_id')
                ->references('id')
                ->on('songs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('charts', function (Blueprint $table): void {
            $table->dropForeign(['song_id']);
            $table->unsignedBigInteger('song_id')->nullable(false)->change();

            $table->foreign('song_id')
                ->references('id')
                ->on('songs')
                ->cascadeOnDelete();
        });
    }
};
