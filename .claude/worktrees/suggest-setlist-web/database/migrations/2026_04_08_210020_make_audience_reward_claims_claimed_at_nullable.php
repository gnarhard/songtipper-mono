<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audience_reward_claims', function (Blueprint $table): void {
            $table->timestamp('claimed_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('audience_reward_claims', function (Blueprint $table): void {
            $table->timestamp('claimed_at')->nullable(false)->change();
        });
    }
};
