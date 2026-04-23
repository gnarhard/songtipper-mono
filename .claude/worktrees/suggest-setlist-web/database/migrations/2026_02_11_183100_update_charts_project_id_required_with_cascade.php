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

        // Remove any legacy global charts before making project_id required.
        DB::table('charts')->whereNull('project_id')->delete();

        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'charts'
            AND COLUMN_NAME = 'project_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        if (! empty($foreignKeys)) {
            Schema::table('charts', function (Blueprint $table) use ($foreignKeys) {
                $table->dropForeign($foreignKeys[0]->CONSTRAINT_NAME);
            });
        }

        Schema::table('charts', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
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
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable()->change();
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('set null');
        });
    }
};
