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
        Schema::dropIfExists('billing_months');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_monthly_tip_cents',
                'billing_month_start_date',
                'billing_skipped_months',
                'billing_skipped_month_dates',
                'billing_catch_up_sent_at',
                'billing_catch_up_declined_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('billing_monthly_tip_cents')->default(0);
            $table->date('billing_month_start_date')->nullable();
            $table->unsignedInteger('billing_skipped_months')->default(0);
            $table->json('billing_skipped_month_dates')->nullable();
            $table->timestamp('billing_catch_up_sent_at')->nullable();
            $table->timestamp('billing_catch_up_declined_at')->nullable();
        });

        Schema::create('billing_months', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('month_start_date');
            $table->unsignedBigInteger('tip_cents')->default(0);
            $table->boolean('was_skipped')->default(false);
            $table->boolean('catch_up_required')->default(false);
            $table->boolean('catch_up_paid')->default(false);
            $table->unsignedBigInteger('amount_charged_cents')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'month_start_date']);
        });
    }
};
