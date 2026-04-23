<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedSmallInteger('min_suggested_setlist_songs')
                ->default(5)
                ->after('public_repertoire_set_id');
            $table->unsignedSmallInteger('max_suggested_setlist_songs')
                ->default(25)
                ->after('min_suggested_setlist_songs');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['min_suggested_setlist_songs', 'max_suggested_setlist_songs']);
        });
    }
};
