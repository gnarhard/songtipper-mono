<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The songs table has two overlapping dedup constraints:
 *   - normalized_key (lowercased, punctuation-stripped title|artist)
 *   - (title, artist)
 *
 * normalized_key is the one the application uses for dedup and merge.
 * The (title, artist) unique is redundant — any two songs that would
 * collide on raw title/artist also collide on normalized_key — and it
 * blocks the "mashups get a fresh Song row" behavior because two
 * mashups with identical display title/artist need distinct rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            $table->dropUnique(['title', 'artist']);
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            $table->unique(['title', 'artist']);
        });
    }
};
