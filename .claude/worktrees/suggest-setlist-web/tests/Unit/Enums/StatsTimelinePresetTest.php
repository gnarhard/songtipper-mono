<?php

declare(strict_types=1);

use App\Enums\StatsTimelinePreset;

it('can be created from valid string values', function (string $value) {
    $preset = StatsTimelinePreset::from($value);

    expect($preset->value)->toBe($value);
})->with([
    'today', 'yesterday', 'this_week', 'last_week',
    'this_month', 'last_month', 'this_year', 'last_year',
    'all_time', 'custom',
]);

it('returns true for isCustom only when Custom', function () {
    expect(StatsTimelinePreset::Custom->isCustom())->toBeTrue();
});

it('returns false for isCustom on non-custom presets', function (StatsTimelinePreset $preset) {
    expect($preset->isCustom())->toBeFalse();
})->with([
    StatsTimelinePreset::Today,
    StatsTimelinePreset::Yesterday,
    StatsTimelinePreset::ThisWeek,
    StatsTimelinePreset::LastWeek,
    StatsTimelinePreset::ThisMonth,
    StatsTimelinePreset::LastMonth,
    StatsTimelinePreset::ThisYear,
    StatsTimelinePreset::LastYear,
    StatsTimelinePreset::AllTime,
]);

it('returns true for usesRelativeMidnightWindow on date-range presets', function (StatsTimelinePreset $preset) {
    expect($preset->usesRelativeMidnightWindow())->toBeTrue();
})->with([
    StatsTimelinePreset::Today,
    StatsTimelinePreset::Yesterday,
    StatsTimelinePreset::ThisWeek,
    StatsTimelinePreset::LastWeek,
    StatsTimelinePreset::ThisMonth,
    StatsTimelinePreset::LastMonth,
    StatsTimelinePreset::ThisYear,
    StatsTimelinePreset::LastYear,
]);

it('returns false for usesRelativeMidnightWindow on AllTime and Custom', function (StatsTimelinePreset $preset) {
    expect($preset->usesRelativeMidnightWindow())->toBeFalse();
})->with([
    StatsTimelinePreset::AllTime,
    StatsTimelinePreset::Custom,
]);
