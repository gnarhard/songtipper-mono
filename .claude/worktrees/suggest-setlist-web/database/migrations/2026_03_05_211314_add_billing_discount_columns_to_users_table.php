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
            $table->string('billing_discount_type')->nullable()->after('billing_plan');
            $table->timestamp('billing_discount_ends_at')->nullable()->after('billing_discount_type');

            $table->index(
                ['billing_discount_type', 'billing_discount_ends_at'],
                'users_billing_discount_lookup_idx'
            );
        });

        DB::table('users')
            ->where('billing_plan', 'monthly')
            ->update(['billing_plan' => 'pro_monthly']);

        DB::table('users')
            ->where('billing_plan', 'annual')
            ->update(['billing_plan' => 'pro_yearly']);

        DB::table('users')
            ->where('billing_status', 'threshold_pending')
            ->update(['billing_status' => 'trialing']);

        DB::table('users')
            ->where('billing_plan', 'lifetime')
            ->update([
                'billing_plan' => 'pro_yearly',
                'billing_discount_type' => 'lifetime',
                'billing_discount_ends_at' => null,
                'billing_status' => 'discounted',
            ]);

        DB::table('users')
            ->where('billing_status', 'lifetime_paid')
            ->update([
                'billing_discount_type' => 'lifetime',
                'billing_discount_ends_at' => null,
                'billing_status' => 'discounted',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_billing_discount_lookup_idx');
            $table->dropColumn([
                'billing_discount_type',
                'billing_discount_ends_at',
            ]);
        });
    }
};
