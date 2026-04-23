<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PerformanceSessionItemStatus;
use App\Models\PerformanceSession;
use App\Models\PerformanceSessionItem;
use App\Models\ProjectSong;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceSessionItem>
 */
class PerformanceSessionItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'performance_session_id' => PerformanceSession::factory(),
            'setlist_set_id' => null,
            'setlist_song_id' => null,
            'project_song_id' => ProjectSong::factory(),
            'status' => PerformanceSessionItemStatus::Pending,
            'order_index' => 0,
            'performed_order_index' => null,
            'performed_at' => null,
            'skipped_at' => null,
            'notes' => null,
        ];
    }
}
