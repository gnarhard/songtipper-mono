<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_checklist_custom_items', function (Blueprint $table) {
            $table->string('section_slug', 100)->after('user_id')->default('');
            $table->index(['user_id', 'section_slug']);
        });
    }

    public function down(): void
    {
        Schema::table('test_checklist_custom_items', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'section_slug']);
            $table->dropColumn('section_slug');
        });
    }
};
