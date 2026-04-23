<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('setlists', function (Blueprint $table) {
            $table->string('folder', 255)->nullable()->after('notes');
            $table->timestamp('archived_at')->nullable()->after('folder');

            $table->index(['project_id', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('setlists', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'archived_at']);
            $table->dropColumn(['folder', 'archived_at']);
        });
    }
};
