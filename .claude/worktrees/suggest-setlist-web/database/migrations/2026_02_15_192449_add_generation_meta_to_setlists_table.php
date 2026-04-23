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
            $table->json('generation_meta')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('setlists', function (Blueprint $table): void {
            $table->dropColumn('generation_meta');
        });
    }
};
