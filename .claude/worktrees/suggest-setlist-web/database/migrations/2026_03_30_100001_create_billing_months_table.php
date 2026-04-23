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
        Schema::create('billing_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('year_month', 7); // YYYY-MM
            $table->unsignedBigInteger('tip_earnings_cents')->default(0);
            $table->string('plan_charged')->nullable(); // pro_monthly, pro_yearly, veteran_monthly
            $table->unsignedInteger('amount_charged_cents')->default(0);
            $table->boolean('was_skipped')->default(false);
            $table->boolean('catch_up_eligible')->default(false);
            $table->boolean('catch_up_paid')->default(false);
            $table->boolean('catch_up_forgiven')->default(false);
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_months');
    }
};
