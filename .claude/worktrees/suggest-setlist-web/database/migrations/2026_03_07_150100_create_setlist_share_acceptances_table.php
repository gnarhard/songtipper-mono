<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setlist_share_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('setlist_share_link_id')
                ->constrained('setlist_share_links')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('copied_setlist_id')
                ->nullable()
                ->constrained('setlists')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['setlist_share_link_id', 'user_id'], 'setlist_share_acceptances_link_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setlist_share_acceptances');
    }
};
