<?php

declare(strict_types=1);

use App\Jobs\RenderChartPages;
use App\Models\Chart;
use Illuminate\Contracts\Queue\ShouldBeUnique;

it('is unique per chart id to prevent duplicate queue fan-out', function () {
    $chart = Chart::factory()->create();

    $job = new RenderChartPages($chart);

    expect(class_implements($job))->toContain(ShouldBeUnique::class)
        ->and($job->uniqueFor)->toBe(600)
        ->and($job->uniqueId())->toBe((string) $chart->id);
});
