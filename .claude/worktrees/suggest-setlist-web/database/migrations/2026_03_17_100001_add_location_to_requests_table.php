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
            $table->string('request_city', 100)->nullable()->after('requested_from_ip');
            $table->string('request_region', 100)->nullable()->after('request_city');
            $table->string('request_country', 10)->nullable()->after('request_region');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['request_city', 'request_region', 'request_country']);
        });
    }
};
