<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_performances', function (Blueprint $table): void {
            $table->foreignId('performance_session_id')
                ->nullable()
                ->after('project_song_id')
                ->constrained('performance_sessions')
                ->nullOnDelete();
            $table->unsignedInteger('performed_order_index')
                ->nullable()
                ->after('setlist_song_id');

            $table->index(
                ['performance_session_id', 'performed_order_index'],
                'song_perf_session_order_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('song_performances', function (Blueprint $table): void {
            $table->dropIndex('song_perf_session_order_idx');
            $table->dropConstrainedForeignId('performance_session_id');
            $table->dropColumn('performed_order_index');
        });
    }
};
