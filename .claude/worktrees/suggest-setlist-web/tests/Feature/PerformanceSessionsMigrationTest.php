<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

it('skips creating performance session tables when they already exist', function (): void {
    expect(Schema::hasTable('performance_sessions'))->toBeTrue();
    expect(Schema::hasTable('performance_session_items'))->toBeTrue();

    /** @var Migration $migration */
    $migration = require base_path('database/migrations/2026_02_15_192447a_create_performance_sessions_tables.php');
    $migration->up();

    expect(Schema::hasTable('performance_sessions'))->toBeTrue();
    expect(Schema::hasTable('performance_session_items'))->toBeTrue();
});
