<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_thresholds', function (Blueprint $table): void {
            $table->string('reward_icon', 32)->nullable()->after('reward_label');
            $table->text('reward_description')->nullable()->after('reward_icon');
        });
    }

    public function down(): void
    {
        Schema::table('reward_thresholds', function (Blueprint $table): void {
            $table->dropColumn(['reward_icon', 'reward_description']);
        });
    }
};
