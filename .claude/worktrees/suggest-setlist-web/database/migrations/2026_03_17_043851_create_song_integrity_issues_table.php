<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('song_integrity_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->string('issue_type', 30);
            $table->string('field', 30)->nullable();
            $table->string('current_value')->nullable();
            $table->string('suggested_value')->nullable();
            $table->text('explanation')->nullable();
            $table->string('severity', 10)->default('warning');
            $table->string('status', 10)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['song_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_integrity_issues');
    }
};
