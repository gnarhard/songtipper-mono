<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charts', function (Blueprint $table) {
            $table->unsignedBigInteger('file_size_bytes')->nullable()->after('page_count');
        });

        Schema::table('chart_renders', function (Blueprint $table) {
            $table->unsignedBigInteger('file_size_bytes')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('charts', function (Blueprint $table) {
            $table->dropColumn('file_size_bytes');
        });

        Schema::table('chart_renders', function (Blueprint $table) {
            $table->dropColumn('file_size_bytes');
        });
    }
};
