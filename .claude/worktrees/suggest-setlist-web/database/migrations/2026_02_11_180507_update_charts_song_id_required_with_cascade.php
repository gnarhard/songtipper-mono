<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
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
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Delete any charts with null song_id (broken uploads)
        DB::table('charts')->whereNull('song_id')->delete();

        // Check if foreign key exists and drop it
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'charts'
            AND COLUMN_NAME = 'song_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        if (! empty($foreignKeys)) {
            Schema::table('charts', function (Blueprint $table) use ($foreignKeys) {
                $table->dropForeign($foreignKeys[0]->CONSTRAINT_NAME);
            });
        }

        Schema::table('charts', function (Blueprint $table) {
            // Make song_id NOT NULL and UNSIGNED to match songs.id
            $table->unsignedBigInteger('song_id')->nullable(false)->change();

            // Recreate foreign key with CASCADE on delete
            $table->foreign('song_id')
                ->references('id')
                ->on('songs')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('charts', function (Blueprint $table) {
            // Drop cascade foreign key
            $table->dropForeign(['song_id']);

            // Make song_id nullable and signed again
            $table->unsignedBigInteger('song_id')->nullable()->change();

            // Recreate original foreign key with SET NULL
            $table->foreign('song_id')
                ->references('id')
                ->on('songs')
                ->onDelete('set null');
        });
    }
};
