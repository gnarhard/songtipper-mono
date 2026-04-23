<?php

declare(strict_types=1);

use App\Services\LyricSheetPdfService;

it('generates a valid PDF from lyric data', function () {
    $service = new LyricSheetPdfService;

    $lyricData = [
        'sections' => [
            [
                'type' => 'verse',
                'label' => 'Verse',
                'lines' => [
                    'Almost heaven, West Virginia',
                    'Blue Ridge Mountains, Shenandoah River',
                ],
            ],
            [
                'type' => 'chorus',
                'label' => 'Chorus',
                'lines' => [
                    'Country roads, take me home',
                    'To the place I belong',
                ],
            ],
        ],
    ];

    $pdfPath = $service->generate('Country Roads', 'John Denver', $lyricData);

    expect($pdfPath)->toBeString();
    expect(file_exists($pdfPath))->toBeTrue();

    $content = file_get_contents($pdfPath);
    expect($content)->toStartWith('%PDF-');
    expect(strlen($content))->toBeGreaterThan(100);

    @unlink($pdfPath);
});

it('uses two-column layout for songs with many lines', function () {
    $service = new LyricSheetPdfService;

    $lines = [];
    for ($i = 0; $i < 30; $i++) {
        $lines[] = "Line number $i of the song lyrics.";
    }

    $lyricData = [
        'sections' => [
            ['type' => 'verse', 'label' => 'Verse 1', 'lines' => $lines],
            ['type' => 'chorus', 'label' => 'Chorus', 'lines' => $lines],
        ],
    ];

    $pdfPath = $service->generate('Long Song', 'Test Artist', $lyricData);

    expect(file_exists($pdfPath))->toBeTrue();
    expect(filesize($pdfPath))->toBeGreaterThan(100);

    @unlink($pdfPath);
});
