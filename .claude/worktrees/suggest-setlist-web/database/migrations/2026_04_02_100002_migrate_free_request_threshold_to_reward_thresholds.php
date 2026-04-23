<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $projects = DB::table('projects')
            ->where('free_request_threshold_cents', '>', 0)
            ->get(['id', 'free_request_threshold_cents']);

        $now = now();
        $rows = $projects->map(fn ($project) => [
            'project_id' => $project->id,
            'threshold_cents' => $project->free_request_threshold_cents,
            'reward_type' => 'free_request',
            'reward_label' => 'Free Song Request',
            'is_repeating' => true,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if (count($rows) > 0) {
            DB::table('reward_thresholds')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('reward_thresholds')
            ->where('reward_type', 'free_request')
            ->delete();
    }
};
