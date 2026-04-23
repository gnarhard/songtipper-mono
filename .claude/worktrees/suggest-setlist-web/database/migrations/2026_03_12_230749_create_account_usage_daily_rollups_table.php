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
        Schema::create('account_usage_daily_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('rollup_date');
            $table->bigInteger('storage_delta_bytes')->default(0);
            $table->unsignedBigInteger('storage_bytes_snapshot')->default(0);
            $table->unsignedInteger('ai_operations')->default(0);
            $table->unsignedInteger('bulk_ai_operations')->default(0);
            $table->unsignedBigInteger('estimated_ai_cost_micros')->default(0);
            $table->unsignedBigInteger('estimated_bandwidth_bytes')->default(0);
            $table->unsignedInteger('queue_failures')->default(0);
            $table->unsignedInteger('render_failures')->default(0);
            $table->unsignedInteger('limit_rejections')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'rollup_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_usage_daily_rollups');
    }
};
