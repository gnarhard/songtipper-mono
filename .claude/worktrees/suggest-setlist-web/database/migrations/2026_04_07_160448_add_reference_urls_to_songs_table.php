<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            $table->string('youtube_video_url', 2048)->nullable()->after('duration_in_seconds');
            $table->string('ultimate_guitar_url', 2048)->nullable()->after('youtube_video_url');
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table): void {
            $table->dropColumn(['youtube_video_url', 'ultimate_guitar_url']);
        });
    }
};
