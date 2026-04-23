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
            $table->boolean('needs_improvement')->default(false)->after('genre');
            $table->unsignedInteger('performance_count')->default(0)->after('needs_improvement');
            $table->timestamp('last_performed_at')->nullable()->after('performance_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_songs', function (Blueprint $table) {
            $table->dropColumn(['needs_improvement', 'performance_count', 'last_performed_at']);
        });
    }
};
