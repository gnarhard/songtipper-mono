<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_batches', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->default('anthropic');
            $table->string('batch_id', 100)->unique();
            $table->string('batch_type', 30)->default('chart_identification');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('request_count')->default(0);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_batches');
    }
};
