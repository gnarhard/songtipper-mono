<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->index(
                ['project_id', 'played_at'],
                'requests_proj_played_idx',
            );
            $table->index(
                ['project_id', 'payment_provider', 'created_at'],
                'requests_proj_payment_created_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex('requests_proj_played_idx');
            $table->dropIndex('requests_proj_payment_created_idx');
        });
    }
};
