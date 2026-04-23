<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->foreignId('performance_session_id')
                ->nullable()
                ->after('audience_profile_id')
                ->constrained('performance_sessions')
                ->nullOnDelete();

            $table->index(['performance_session_id', 'audience_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropIndex(['performance_session_id', 'audience_profile_id']);
            $table->dropConstrainedForeignId('performance_session_id');
        });
    }
};
