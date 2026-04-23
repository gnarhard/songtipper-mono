<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedInteger('free_request_threshold_cents')->default(4000)->after('min_tip_cents');
        });

        Schema::table('audience_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('cumulative_tip_cents')->default(0)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('free_request_threshold_cents');
        });

        Schema::table('audience_profiles', function (Blueprint $table): void {
            $table->dropColumn('cumulative_tip_cents');
        });
    }
};
