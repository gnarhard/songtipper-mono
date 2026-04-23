<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('billing_yearly_nudge_sent_at')->nullable()->after('billing_catch_up_declined_at');
        });

        // Lower activation threshold from $400 back to $200.
        DB::table('users')
            ->where('billing_threshold_cents', 40000)
            ->update(['billing_threshold_cents' => 20000]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('billing_threshold_cents', 20000)
            ->update(['billing_threshold_cents' => 40000]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('billing_yearly_nudge_sent_at');
        });
    }
};
