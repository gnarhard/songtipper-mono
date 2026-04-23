<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_usage_counters', function (Blueprint $table) {
            $table->unsignedBigInteger('audio_mp3_bytes')->default(0)->after('performer_image_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('account_usage_counters', function (Blueprint $table) {
            $table->dropColumn('audio_mp3_bytes');
        });
    }
};
