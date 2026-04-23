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
        Schema::table('users', function (Blueprint $table) {
            $table->string('billing_plan')->nullable()->after('trial_ends_at');
            $table->string('billing_status')->default('setup_required')->after('billing_plan');
            $table->unsignedInteger('billing_threshold_cents')->default(20000)->after('billing_status');
            $table->unsignedInteger('billing_total_paid_tips_cents')->default(0)->after('billing_threshold_cents');
            $table->timestamp('billing_activated_at')->nullable()->after('billing_total_paid_tips_cents');
            $table->timestamp('billing_last_attempted_at')->nullable()->after('billing_activated_at');
            $table->string('billing_last_error_code')->nullable()->after('billing_last_attempted_at');
            $table->text('billing_last_error_message')->nullable()->after('billing_last_error_code');

            $table->index(['billing_status', 'billing_plan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['billing_status', 'billing_plan']);

            $table->dropColumn([
                'billing_plan',
                'billing_status',
                'billing_threshold_cents',
                'billing_total_paid_tips_cents',
                'billing_activated_at',
                'billing_last_attempted_at',
                'billing_last_error_code',
                'billing_last_error_message',
            ]);
        });
    }
};
