<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('billing_status', 'setup_required')
            ->update([
                'billing_plan' => 'free',
                'billing_activated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('billing_plan', 'free')
            ->where('billing_status', 'setup_required')
            ->update([
                'billing_plan' => null,
                'billing_activated_at' => null,
            ]);
    }
};
