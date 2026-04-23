<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setlists', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('name');
        });

        Schema::table('setlist_sets', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('name');
        });

        Schema::table('setlist_songs', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('order_index');
        });
    }

    public function down(): void
    {
        Schema::table('setlist_songs', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });

        Schema::table('setlist_sets', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });

        Schema::table('setlists', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });
    }
};
