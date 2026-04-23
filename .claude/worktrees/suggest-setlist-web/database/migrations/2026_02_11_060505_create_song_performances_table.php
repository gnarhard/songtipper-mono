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
        Schema::create('song_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source'); // repertoire|setlist
            $table->foreignId('setlist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('set_id')->nullable()->constrained('setlist_sets')->nullOnDelete();
            $table->foreignId('setlist_song_id')->nullable()->constrained('setlist_songs')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index('project_song_id');
            $table->index(['project_id', 'performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('song_performances');
    }
};
