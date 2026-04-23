<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('stripe_account_id')->unique();
            $table->char('country', 2)->nullable();
            $table->char('default_currency', 3)->nullable();
            $table->boolean('details_submitted')->default(false);
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->json('requirements_currently_due')->nullable();
            $table->json('requirements_past_due')->nullable();
            $table->string('requirements_disabled_reason')->nullable();
            $table->string('status')->default('pending');
            $table->string('status_reason')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payout_accounts');
    }
};
