<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charts', function (Blueprint $table) {
            $table->string('import_status', 20)->nullable()->after('has_renders');
            $table->text('import_error')->nullable()->after('import_status');
        });
    }

    public function down(): void
    {
        Schema::table('charts', function (Blueprint $table) {
            $table->dropColumn(['import_status', 'import_error']);
        });
    }
};
