<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('audience_profile_id')
                ->nullable()
                ->constrained('audience_profiles')
                ->nullOnDelete();
            $table->string('actor_key', 191);
            $table->string('method', 10);
            $table->string('path', 191);
            $table->string('idempotency_key', 191);
            $table->unsignedSmallInteger('status_code');
            $table->longText('response_json');
            $table->timestamps();

            $table->unique(
                ['actor_key', 'method', 'path', 'idempotency_key'],
                'idempotency_actor_method_path_key_unique'
            );
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
