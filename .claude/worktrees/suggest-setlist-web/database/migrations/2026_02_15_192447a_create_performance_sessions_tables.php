<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('performance_sessions')) {
            Schema::create('performance_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('setlist_id')->nullable()->constrained()->nullOnDelete();
                $table->string('mode', 16)->default('manual');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('seed')->nullable();
                $table->string('generation_version', 32)->nullable();
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();

                $table->index(['project_id', 'is_active']);
                $table->index(['project_id', 'started_at']);
            });
        }

        if (! Schema::hasTable('performance_session_items')) {
            Schema::create('performance_session_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('performance_session_id')
                    ->constrained('performance_sessions')
                    ->cascadeOnDelete();
                $table->foreignId('setlist_set_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('setlist_song_id')
                    ->nullable()
                    ->constrained('setlist_songs')
                    ->nullOnDelete();
                $table->foreignId('project_song_id')->constrained()->cascadeOnDelete();
                $table->string('status', 16)->default('pending');
                $table->unsignedInteger('order_index')->default(0);
                $table->unsignedInteger('performed_order_index')->nullable();
                $table->timestamp('performed_at')->nullable();
                $table->timestamp('skipped_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(
                    ['performance_session_id', 'status'],
                    'perf_items_session_status_idx'
                );
                $table->index(
                    ['performance_session_id', 'order_index'],
                    'perf_items_session_order_idx'
                );
                $table->index(
                    ['performance_session_id', 'performed_order_index'],
                    'perf_items_session_performed_order_idx'
                );
                $table->unique(
                    ['performance_session_id', 'setlist_song_id'],
                    'perf_items_unique_session_song'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_session_items');
        Schema::dropIfExists('performance_sessions');
    }
};
