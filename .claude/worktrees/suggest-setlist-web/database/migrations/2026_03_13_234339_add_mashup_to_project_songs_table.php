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
        Schema::table('project_songs', function (Blueprint $table) {
            $table->boolean('mashup')->default(false)->after('instrumental');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_songs', function (Blueprint $table) {
            $table->dropColumn('mashup');
        });
    }
};
