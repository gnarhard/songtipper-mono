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
            $table->unsignedInteger('quick_tip_1_cents')
                ->default(2000)
                ->after('min_tip_cents');
            $table->unsignedInteger('quick_tip_2_cents')
                ->default(1500)
                ->after('quick_tip_1_cents');
            $table->unsignedInteger('quick_tip_3_cents')
                ->default(1000)
                ->after('quick_tip_2_cents');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn([
                'quick_tip_1_cents',
                'quick_tip_2_cents',
                'quick_tip_3_cents',
            ]);
        });
    }
};
