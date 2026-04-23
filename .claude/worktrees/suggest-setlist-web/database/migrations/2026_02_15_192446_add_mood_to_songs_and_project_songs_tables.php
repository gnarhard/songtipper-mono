<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            $table->string('mood', 64)->nullable()->after('genre')->index();
        });

        Schema::table('project_songs', function (Blueprint $table): void {
            $table->string('mood', 64)->nullable()->after('genre')->index();
        });
    }

    public function down(): void
    {
        Schema::table('project_songs', function (Blueprint $table): void {
            $table->dropIndex(['mood']);
            $table->dropColumn('mood');
        });

        Schema::table('songs', function (Blueprint $table): void {
            $table->dropIndex(['mood']);
            $table->dropColumn('mood');
        });
    }
};
