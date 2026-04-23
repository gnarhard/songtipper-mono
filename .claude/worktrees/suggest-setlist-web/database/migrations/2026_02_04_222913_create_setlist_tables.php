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
        Schema::create('setlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });

        Schema::create('setlist_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setlist_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();

            $table->index(['setlist_id', 'order_index']);
        });

        Schema::create('setlist_songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('setlist_sets')->cascadeOnDelete();
            $table->foreignId('project_song_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();

            $table->index(['set_id', 'order_index']);
            $table->unique(['set_id', 'project_song_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setlist_songs');
        Schema::dropIfExists('setlist_sets');
        Schema::dropIfExists('setlists');
    }
};
