<?php

declare(strict_types=1);

use App\Models\Project;

test('it normalizes quick tip amounts to whole-dollar cents', function () {
    expect(Project::normalizeQuickTipAmounts([2301, 1705, 999]))
        ->toBe([2400, 1800, 1000]);
});
