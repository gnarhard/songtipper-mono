<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_reward_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audience_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_threshold_id')->constrained()->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamps();

            $table->index(['audience_profile_id', 'reward_threshold_id'], 'arc_profile_threshold_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_reward_claims');
    }
};
