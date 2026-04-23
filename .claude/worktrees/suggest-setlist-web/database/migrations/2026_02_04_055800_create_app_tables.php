<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('min_tip_cents')->default(500);
            $table->boolean('is_accepting_requests')->default(true);
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // owner, member, readonly
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('songs', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('artist');
            $table->string('normalized_key')->unique();
            $table->timestamps();

            $table->unique(['title', 'artist']);
        });

        Schema::create('project_songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->string('energy_level')->nullable();
            $table->string('era')->nullable();
            $table->string('genre')->nullable();
            $table->string('performed_musical_key', 8)->nullable();
            $table->string('original_musical_key', 8)->nullable();
            $table->string('tuning', 50)->nullable();
            $table->unsignedInteger('duration_in_seconds')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'song_id']);
        });

        Schema::create('charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('song_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('project_label')->nullable();
            $table->string('storage_disk')->default('r2');
            $table->string('storage_path_pdf');
            $table->string('original_filename')->nullable();
            $table->unsignedTinyInteger('page_count')->nullable();
            $table->boolean('has_renders')->default(false);
            $table->timestamps();

            $table->index(['owner_user_id', 'song_id']);
            $table->index(['project_id']);
        });

        Schema::create('chart_renders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chart_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('page_number');
            $table->string('theme'); // light, dark
            $table->string('storage_path_image');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamps();

            $table->unique(['chart_id', 'page_number', 'theme']);
        });

        Schema::create('project_song_charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chart_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'song_id', 'chart_id']);
        });

        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('tip_amount_cents');
            $table->unsignedInteger('score_cents'); // same as tip for now
            $table->text('note')->nullable();
            $table->string('status'); // active, played
            $table->string('requested_from_ip')->nullable();
            $table->string('payment_provider')->default('stripe');
            $table->string('payment_intent_id')->nullable()->unique();
            $table->timestamp('played_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status', 'tip_amount_cents', 'created_at'], 'requests_queue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_pages');
        Schema::dropIfExists('chart_renders');
        Schema::dropIfExists('charts');
        Schema::dropIfExists('requests');
        Schema::dropIfExists('project_songs');
        Schema::dropIfExists('songs');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
    }
};
