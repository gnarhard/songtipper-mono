<?php

declare(strict_types=1);

use App\Jobs\GenerateLyricSheet;
use App\Jobs\RenderChartPages;
use App\Models\Chart;
use App\Models\Project;
use App\Models\User;
use App\Services\AccountUsageService;
use App\Services\LyricSheetPdfService;
use App\Services\SongMetadataAiRepository;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('filesystems.chart', 'r2');
    Storage::fake('r2');
    Queue::fake([RenderChartPages::class]);

    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);

    $this->chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'source_type' => 'ai_generated',
        'import_status' => 'generating',
        'storage_path_pdf' => '',
        'has_renders' => false,
        'page_count' => 0,
    ]);
});

it('generates lyric data, creates PDF, stores it, and dispatches render job', function () {
    $lyricData = [
        'sections' => [
            [
                'type' => 'verse',
                'label' => 'Verse 1',
                'lines' => [
                    'Hello world, this is a test',
                    'Another line of lyrics here',
                ],
            ],
            [
                'type' => 'chorus',
                'label' => 'Chorus',
                'lines' => [
                    'This is the chorus line',
                ],
            ],
        ],
    ];

    $mockAi = Mockery::mock(SongMetadataAiRepository::class);
    $mockAi->shouldReceive('generateLyricSheet')
        ->once()
        ->with('Test Song', 'Test Artist', null)
        ->andReturn($lyricData);

    $tempPdf = storage_path('app/tmp/test-lyric-sheet.pdf');
    if (! is_dir(dirname($tempPdf))) {
        mkdir(dirname($tempPdf), 0755, true);
    }
    file_put_contents($tempPdf, '%PDF-1.4 fake content');

    $mockPdf = Mockery::mock(LyricSheetPdfService::class);
    $mockPdf->shouldReceive('generate')
        ->once()
        ->with('Test Song', 'Test Artist', $lyricData)
        ->andReturn($tempPdf);

    $mockUsage = Mockery::mock(AccountUsageService::class);
    $mockUsage->shouldReceive('recordAiOperation')->once();
    $mockUsage->shouldReceive('incrementStorageBytes')->once();

    $job = new GenerateLyricSheet(
        $this->chart->id,
        'Test Song',
        'Test Artist',
    );

    $job->handle($mockAi, $mockPdf, $mockUsage);

    $this->chart->refresh();
    expect($this->chart->import_status)->toBeNull();
    expect($this->chart->import_error)->toBeNull();
    expect($this->chart->import_metadata)->toBe($lyricData);
    expect($this->chart->source_sha256)->not->toBe('');
    expect($this->chart->storage_path_pdf)->toContain('source.pdf');

    Storage::disk('r2')->assertExists($this->chart->storage_path_pdf);
    Queue::assertPushed(RenderChartPages::class);
});

it('marks chart as failed when AI returns null', function () {
    $mockAi = Mockery::mock(SongMetadataAiRepository::class);
    $mockAi->shouldReceive('generateLyricSheet')
        ->once()
        ->andReturn(null);

    $mockPdf = Mockery::mock(LyricSheetPdfService::class);

    $mockUsage = Mockery::mock(AccountUsageService::class);
    $mockUsage->shouldReceive('recordAiOperation')->once();

    $job = new GenerateLyricSheet(
        $this->chart->id,
        'Unknown Song',
        'Unknown Artist',
    );

    $job->handle($mockAi, $mockPdf, $mockUsage);

    $this->chart->refresh();
    expect($this->chart->import_status)->toBe('failed');
    expect($this->chart->import_error)->toContain('could not generate');

    Queue::assertNothingPushed();
});
