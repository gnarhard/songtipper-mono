<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('charts', 'source_sha256')) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->string('source_sha256', 64)
                    ->nullable()
                    ->after('storage_path_pdf');
                $table->index('source_sha256');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('charts', 'source_sha256')) {
            Schema::table('charts', function (Blueprint $table): void {
                $table->dropIndex(['source_sha256']);
                $table->dropColumn('source_sha256');
            });
        }
    }
};
