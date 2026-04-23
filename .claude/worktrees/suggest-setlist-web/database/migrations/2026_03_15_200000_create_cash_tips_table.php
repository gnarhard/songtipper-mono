<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_tips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedInteger('amount_cents');
            $table->date('local_date');
            $table->string('timezone', 64);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();

            $table->index(['project_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_tips');
    }
};
