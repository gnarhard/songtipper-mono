<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_token', 64);
            $table->string('last_seen_ip')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'visitor_token']);
            $table->index(['project_id', 'last_seen_at']);
        });

        Schema::table('requests', function (Blueprint $table): void {
            $table->foreignId('audience_profile_id')
                ->nullable()
                ->after('project_id')
                ->constrained('audience_profiles')
                ->nullOnDelete();
        });

        Schema::create('audience_achievements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audience_profile_id')
                ->constrained('audience_profiles')
                ->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('requests')->nullOnDelete();
            $table->string('code', 100);
            $table->string('title', 120);
            $table->string('description', 255);
            $table->json('meta')->nullable();
            $table->timestamp('earned_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['audience_profile_id', 'code']);
            $table->index(['audience_profile_id', 'notified_at']);
            $table->index(['project_id', 'earned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_achievements');

        Schema::table('requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('audience_profile_id');
        });

        Schema::dropIfExists('audience_profiles');
    }
};
