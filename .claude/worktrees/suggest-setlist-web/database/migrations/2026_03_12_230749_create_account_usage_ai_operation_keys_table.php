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
        $tableName = 'account_usage_ai_operation_keys';

        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('operation_key');
                $table->string('provider', 32);
                $table->string('category', 32);
                $table->timestamp('happened_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex($tableName, ['user_id', 'operation_key'], 'unique')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unique(
                    ['user_id', 'operation_key'],
                    'acct_usage_ai_op_keys_user_opkey_uniq',
                );
            });
        }

        if (! Schema::hasIndex($tableName, ['user_id', 'category', 'created_at'])) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index(
                    ['user_id', 'category', 'created_at'],
                    'acct_usage_ai_op_keys_user_cat_created_idx',
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_usage_ai_operation_keys');
    }
};
