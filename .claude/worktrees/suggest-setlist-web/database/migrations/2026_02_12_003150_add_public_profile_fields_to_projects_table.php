<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('performer_info_url', 2048)->nullable()->after('slug');
            $table->string('performer_profile_image_path', 2048)->nullable()->after('performer_info_url');
            $table->boolean('is_accepting_original_requests')->default(true)->after('is_accepting_requests');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'performer_info_url',
                'performer_profile_image_path',
                'is_accepting_original_requests',
            ]);
        });
    }
};
