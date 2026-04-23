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
            $table->string('header_banner_image_path', 2048)->nullable()->after('performer_profile_image_path');
            $table->string('background_image_path', 2048)->nullable()->after('header_banner_image_path');
            $table->string('brand_color_hex', 7)->nullable()->after('background_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn([
                'header_banner_image_path',
                'background_image_path',
                'brand_color_hex',
            ]);
        });
    }
};
