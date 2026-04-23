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
        Schema::create('account_usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->unsignedBigInteger('chart_pdf_bytes')->default(0);
            $table->unsignedBigInteger('chart_render_bytes')->default(0);
            $table->unsignedBigInteger('performer_image_bytes')->default(0);
            $table->unsignedBigInteger('lifetime_ai_operations')->default(0);
            $table->unsignedBigInteger('lifetime_estimated_ai_cost_micros')->default(0);
            $table->unsignedBigInteger('lifetime_estimated_bandwidth_bytes')->default(0);
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('inactivity_warning_sent_at')->nullable();
            $table->timestamp('archived_render_images_at')->nullable();
            $table->string('review_state', 32)->default('clear');
            $table->string('review_reason')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->json('warning_markers')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_usage_counters');
    }
};
