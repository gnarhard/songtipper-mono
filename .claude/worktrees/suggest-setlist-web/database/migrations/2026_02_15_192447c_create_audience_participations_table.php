<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_participations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_session_id')
                ->constrained('performance_sessions')
                ->cascadeOnDelete();
            $table->foreignId('audience_profile_id')
                ->constrained('audience_profiles')
                ->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(
                ['performance_session_id', 'audience_profile_id'],
                'aud_part_perf_session_profile_unique',
            );
            $table->index(
                ['project_id', 'performance_session_id', 'last_seen_at'],
                'aud_part_project_session_seen_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_participations');
    }
};
