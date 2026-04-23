<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_songs', function (Blueprint $table) {
            $table->string('energy_level')->nullable()->after('song_id');
            $table->string('genre', 50)->nullable()->after('energy_level');
        });
    }

    public function down(): void
    {
        Schema::table('project_songs', function (Blueprint $table) {
            $table->dropColumn(['energy_level', 'genre']);
        });
    }
};
