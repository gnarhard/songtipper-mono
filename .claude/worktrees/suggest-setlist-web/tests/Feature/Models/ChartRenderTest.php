<?php

declare(strict_types=1);

use App\Enums\ChartTheme;
use App\Models\Chart;
use App\Models\ChartRender;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('belongs to a chart', function () {
    $model = new ChartRender;
    $relation = $model->chart();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Chart::class);
});

it('casts theme as ChartTheme enum', function () {
    $render = ChartRender::factory()->create([
        'theme' => 'light',
    ]);

    expect($render->refresh()->theme)->toBe(ChartTheme::Light);
});

it('casts page_number as integer', function () {
    $render = ChartRender::factory()->create([
        'page_number' => 2,
    ]);

    expect($render->refresh()->page_number)->toBeInt();
});
