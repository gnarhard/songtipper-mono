<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_page_user_prefs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->decimal('zoom_scale', 6, 3)->default(1.0);
            $table->decimal('offset_dx', 10, 3)->default(0);
            $table->decimal('offset_dy', 10, 3)->default(0);
            $table->timestamps();

            $table->unique(
                ['chart_id', 'owner_user_id', 'page_number'],
                'chart_page_user_prefs_unique'
            );
            $table->index(['owner_user_id', 'chart_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_page_user_prefs');
    }
};
