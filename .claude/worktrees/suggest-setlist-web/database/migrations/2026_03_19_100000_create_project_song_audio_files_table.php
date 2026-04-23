<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_song_audio_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_song_id')->constrained('project_songs')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('storage_disk', 20);
            $table->string('storage_path');
            $table->string('original_filename');
            $table->string('label', 100)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('source_sha256', 64);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_song_id', 'sort_order']);
            $table->unique(['project_song_id', 'source_sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_song_audio_files');
    }
};
