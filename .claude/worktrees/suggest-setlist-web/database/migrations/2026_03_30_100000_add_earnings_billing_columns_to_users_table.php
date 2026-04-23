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
            $table->unsignedBigInteger('billing_monthly_tip_cents')->default(0)->after('billing_total_paid_tips_cents');
            $table->date('billing_month_start_date')->nullable()->after('billing_monthly_tip_cents');
            $table->unsignedTinyInteger('billing_skipped_months')->default(0)->after('billing_month_start_date');
            $table->json('billing_skipped_month_dates')->nullable()->after('billing_skipped_months');
            $table->timestamp('billing_catch_up_sent_at')->nullable()->after('billing_skipped_month_dates');
            $table->timestamp('billing_catch_up_declined_at')->nullable()->after('billing_catch_up_sent_at');
            $table->timestamp('billing_grace_period_started_at')->nullable()->after('billing_catch_up_declined_at');
            $table->boolean('is_top_earner')->default(false)->after('billing_last_error_message');
            $table->boolean('is_verified_earner')->default(false)->after('is_top_earner');
        });

        // Update activation threshold to $400 (was $200).
        DB::table('users')
            ->where('billing_threshold_cents', 20000)
            ->update(['billing_threshold_cents' => 40000]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'billing_monthly_tip_cents',
                'billing_month_start_date',
                'billing_skipped_months',
                'billing_skipped_month_dates',
                'billing_catch_up_sent_at',
                'billing_catch_up_declined_at',
                'billing_grace_period_started_at',
                'is_top_earner',
                'is_verified_earner',
            ]);
        });

        DB::table('users')
            ->where('billing_threshold_cents', 40000)
            ->update(['billing_threshold_cents' => 20000]);
    }
};
