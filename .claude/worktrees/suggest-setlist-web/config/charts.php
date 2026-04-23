<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Chart Render DPI
    |--------------------------------------------------------------------------
    |
    | Higher DPI produces sharper chart renders but increases CPU, memory,
    | processing time, and output image size. 300 is a good quality default.
    |
    */

    'render_dpi' => (int) env('CHART_RENDER_DPI', 300),

    /*
    |--------------------------------------------------------------------------
    | Chart Render Queue
    |--------------------------------------------------------------------------
    |
    | Render jobs are CPU-heavy. Keep them on a dedicated queue so they
    | don't block lighter queue workloads.
    |
    */

    'render_queue' => (string) env('CHART_RENDER_QUEUE', 'renders'),

    /*
    |--------------------------------------------------------------------------
    | Chart Identification Queue
    |--------------------------------------------------------------------------
    |
    | Gemini identification jobs are network-heavy. Keeping them on their own
    | queue improves throughput and isolates failures.
    |
    */

    'identification_queue' => (string) env('CHART_IDENTIFICATION_QUEUE', 'imports'),

    /*
    |--------------------------------------------------------------------------
    | Chart Render PNG Compression Level
    |--------------------------------------------------------------------------
    |
    | PNG compression is lossless. Lower values are faster to encode and
    | produce larger files. Higher values are slower but smaller.
    |
    */

    'png_compression_level' => (int) env('CHART_RENDER_PNG_COMPRESSION_LEVEL', 3),

    /*
    |--------------------------------------------------------------------------
    | Chart Render Page Concurrency
    |--------------------------------------------------------------------------
    |
    | Maximum number of PDF pages to render in parallel within a single job
    | using forked child processes. Each child loads one rasterized page into
    | Imagick, so higher values trade memory for wall-clock speed. Only
    | effective when the pcntl extension is available.
    |
    */

    'render_page_concurrency' => (int) env('CHART_RENDER_PAGE_CONCURRENCY', 4),

    /*
    |--------------------------------------------------------------------------
    | Chart Render Concurrency
    |--------------------------------------------------------------------------
    |
    | Maximum number of RenderChartPages jobs that may execute simultaneously
    | across all workers. Additional jobs are released back to the queue and
    | retried once a slot opens. Keep this low on memory-constrained servers
    | since each render loads a full PDF into Imagick.
    |
    | Currently enforced via WithoutOverlapping (single-slot mutex).
    |
    */

    'render_concurrency' => (int) env('CHART_RENDER_CONCURRENCY', 1),
];
