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
        Schema::create('chart_annotation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->string('local_version_id');
            $table->string('base_version_id')->nullable();
            $table->json('strokes');
            $table->timestamp('client_created_at');
            $table->timestamps();

            $table->unique(
                ['chart_id', 'owner_user_id', 'page_number', 'local_version_id'],
                'chart_annotations_unique_local_version'
            );
            $table->index(['chart_id', 'page_number', 'created_at'], 'chart_annotations_chart_page_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_annotation_versions');
    }
};
