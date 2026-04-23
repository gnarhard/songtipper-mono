<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('can resume the account usage ai operation key migration after a partial index failure', function (): void {
    /** @var Migration $migration */
    $migration = require base_path('database/migrations/2026_03_12_230749_create_account_usage_ai_operation_keys_table.php');

    $migration->down();

    Schema::create('account_usage_ai_operation_keys', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('operation_key');
        $table->string('provider', 32);
        $table->string('category', 32);
        $table->timestamp('happened_at')->nullable();
        $table->timestamps();

        $table->unique(['user_id', 'operation_key']);
    });

    expect(Schema::hasTable('account_usage_ai_operation_keys'))->toBeTrue();
    expect(Schema::hasIndex('account_usage_ai_operation_keys', ['user_id', 'operation_key'], 'unique'))->toBeTrue();
    expect(Schema::hasIndex('account_usage_ai_operation_keys', ['user_id', 'category', 'created_at']))->toBeFalse();

    $migration->up();

    expect(Schema::hasIndex('account_usage_ai_operation_keys', ['user_id', 'operation_key'], 'unique'))->toBeTrue();
    expect(Schema::hasIndex('account_usage_ai_operation_keys', ['user_id', 'category', 'created_at']))->toBeTrue();
});
