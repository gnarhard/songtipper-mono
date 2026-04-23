<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_release_policies', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 16);
            $table->string('latest_version', 32);
            $table->unsignedInteger('latest_build_number');
            $table->string('store_url', 2048)->nullable();
            $table->string('archive_url', 2048)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->unique('platform', 'app_rel_policies_platform_uniq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_release_policies');
    }
};
