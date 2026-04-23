<?php

declare(strict_types=1);

use App\Enums\ChartTheme;
use App\Jobs\RenderChartPages;
use App\Models\Chart;
use App\Models\ChartRender;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick extension is required for chart render tests.');
    }

    if (! renderJobHasGhostscriptBinary()) {
        $this->markTestSkipped('Ghostscript (gs) is required for chart render tests.');
    }

    config()->set('filesystems.chart', 'r2');
    Storage::fake('r2');

    $this->owner = User::factory()->create();
});

it('renders light and dark theme pages for a chart', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
        'storage_path_pdf' => "charts/{$this->owner->id}/test-chart/source.pdf",
        'has_renders' => false,
        'page_count' => 0,
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, renderJobTestChartPdfContent());

    $job = new RenderChartPages($chart);
    $job->handle();

    $chart->refresh();

    expect($chart->has_renders)->toBeTrue();
    expect($chart->page_count)->toBe(1);

    $renders = $chart->renders;
    expect($renders)->toHaveCount(2);

    $lightRender = $renders->firstWhere('theme', ChartTheme::Light);
    $darkRender = $renders->firstWhere('theme', ChartTheme::Dark);

    expect($lightRender)->not->toBeNull();
    expect($darkRender)->not->toBeNull();
    expect($lightRender->page_number)->toBe(1);
    expect($darkRender->page_number)->toBe(1);

    Storage::disk('r2')->assertExists($lightRender->storage_path_image);
    Storage::disk('r2')->assertExists($darkRender->storage_path_image);
});

it('deletes existing renders before creating new ones', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
        'storage_path_pdf' => "charts/{$this->owner->id}/test-chart/source.pdf",
        'has_renders' => true,
        'page_count' => 1,
    ]);

    $staleRender = ChartRender::factory()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Light,
        'storage_path_image' => "charts/{$this->owner->id}/{$chart->id}/renders/light/stale-page.png",
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, renderJobTestChartPdfContent());

    $job = new RenderChartPages($chart);
    $job->handle();

    expect(ChartRender::find($staleRender->id))->toBeNull();
    expect($chart->renders()->count())->toBe(2);
});

it('cleans up temporary files after rendering', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
        'storage_path_pdf' => "charts/{$this->owner->id}/test-chart/source.pdf",
        'has_renders' => false,
        'page_count' => 0,
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, renderJobTestChartPdfContent());

    $pattern = sys_get_temp_dir()."/chart-render-{$chart->id}-*";
    $beforeTempDirs = collect(glob($pattern) ?: [])
        ->filter(fn ($path) => is_dir($path))
        ->values()
        ->all();

    $job = new RenderChartPages($chart);
    $job->handle();

    $afterTempDirs = collect(glob($pattern) ?: [])
        ->filter(fn ($path) => is_dir($path))
        ->values()
        ->all();

    $newTempDirs = array_values(array_diff($afterTempDirs, $beforeTempDirs));
    expect($newTempDirs)->toBe([]);
});

it('generates correct storage paths for renders', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
        'storage_path_pdf' => "charts/{$this->owner->id}/test-chart/source.pdf",
        'has_renders' => false,
        'page_count' => 0,
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, renderJobTestChartPdfContent());

    $job = new RenderChartPages($chart);
    $job->handle();

    $lightRender = $chart->renders()->where('theme', ChartTheme::Light)->first();
    $darkRender = $chart->renders()->where('theme', ChartTheme::Dark)->first();

    $expectedLightPath = "charts/{$this->owner->id}/{$chart->id}/renders/light/page-1.png";
    $expectedDarkPath = "charts/{$this->owner->id}/{$chart->id}/renders/dark/page-1.png";

    expect($lightRender->storage_path_image)->toBe($expectedLightPath);
    expect($darkRender->storage_path_image)->toBe($expectedDarkPath);
});

it('keeps existing renders when pdf processing fails', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
        'storage_path_pdf' => "charts/{$this->owner->id}/test-chart/source.pdf",
        'has_renders' => true,
        'page_count' => 1,
    ]);

    $existingRender = ChartRender::factory()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Light,
        'storage_path_image' => "charts/{$this->owner->id}/{$chart->id}/renders/light/page-1.png",
    ]);

    $job = new RenderChartPages($chart);

    expect(fn () => $job->handle())->toThrow(Exception::class);
    expect($chart->fresh()->renders()->whereKey($existingRender->id)->exists())->toBeTrue();
    expect($chart->fresh()->renders()->count())->toBe(1);
});

it('deletes partially uploaded renders when a later page theme fails', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
        'storage_path_pdf' => "charts/{$this->owner->id}/test-chart/source.pdf",
        'has_renders' => false,
        'page_count' => 0,
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, renderJobTestChartPdfContent());

    $expectedLightPath = "charts/{$this->owner->id}/{$chart->id}/renders/light/page-1.png";

    $job = new class($chart) extends RenderChartPages
    {
        protected function applyThemePaletteToImage(Imagick $image, ChartTheme $theme, bool $sourceBackgroundIsDark): void
        {
            if ($theme === ChartTheme::Dark) {
                throw new RuntimeException('Forced dark theme failure');
            }

            parent::applyThemePaletteToImage($image, $theme, $sourceBackgroundIsDark);
        }
    };

    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'Forced dark theme failure');

    Storage::disk('r2')->assertMissing($expectedLightPath);
    expect($chart->fresh()->renders()->count())->toBe(0);
});

it('produces larger render dimensions with higher dpi', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
        'storage_path_pdf' => "charts/{$this->owner->id}/dpi-test/source.pdf",
        'has_renders' => false,
        'page_count' => 0,
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, renderJobTestChartPdfContent());

    config()->set('charts.render_dpi', 150);
    (new RenderChartPages($chart))->handle();

    $lowDpiRender = $chart->fresh()->renders()->where('theme', ChartTheme::Light)->firstOrFail();
    $lowDimensions = pngDimensions(Storage::disk('r2')->get($lowDpiRender->storage_path_image));

    config()->set('charts.render_dpi', 300);
    (new RenderChartPages($chart->fresh()))->handle();

    $highDpiRender = $chart->fresh()->renders()->where('theme', ChartTheme::Light)->firstOrFail();
    $highDimensions = pngDimensions(Storage::disk('r2')->get($highDpiRender->storage_path_image));

    expect($highDimensions['width'])->toBeGreaterThan($lowDimensions['width']);
    expect($highDimensions['height'])->toBeGreaterThan($lowDimensions['height']);
});

it('clamps configured render dpi to supported bounds', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
    ]);

    $jobForChart = fn () => new class($chart) extends RenderChartPages
    {
        public function exposedRenderDpi(): int
        {
            return $this->renderDpi();
        }
    };

    config()->set('charts.render_dpi', 10);
    expect($jobForChart()->exposedRenderDpi())->toBe(72);

    config()->set('charts.render_dpi', 320);
    expect($jobForChart()->exposedRenderDpi())->toBe(320);

    config()->set('charts.render_dpi', 1000);
    expect($jobForChart()->exposedRenderDpi())->toBe(600);
});

it('clamps configured png compression level to supported bounds', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
    ]);

    $jobForChart = fn () => new class($chart) extends RenderChartPages
    {
        public function exposedPngCompressionLevel(): int
        {
            return $this->pngCompressionLevel();
        }
    };

    config()->set('charts.png_compression_level', -1);
    expect($jobForChart()->exposedPngCompressionLevel())->toBe(0);

    config()->set('charts.png_compression_level', 4);
    expect($jobForChart()->exposedPngCompressionLevel())->toBe(4);

    config()->set('charts.png_compression_level', 99);
    expect($jobForChart()->exposedPngCompressionLevel())->toBe(9);
});

it('uses a unique lock per chart to avoid duplicate queued render jobs', function () {
    $song = Song::factory()->create();
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
    ]);

    $job = new RenderChartPages($chart);

    expect($job->uniqueFor)->toBe(600)
        ->and($job->uniqueId())->toBe((string) $chart->id);
});

function renderJobHasGhostscriptBinary(): bool
{
    foreach (['/opt/homebrew/bin/gs', '/usr/local/bin/gs', '/usr/bin/gs'] as $binaryPath) {
        if (is_executable($binaryPath)) {
            return true;
        }
    }

    return trim((string) shell_exec('command -v gs 2>/dev/null')) !== '';
}

function renderJobTestChartPdfContent(): string
{
    return <<<'PDF'
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 300] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>
endobj
4 0 obj
<< /Length 44 >>
stream
BT
/F1 24 Tf
40 150 Td
(Test PDF) Tj
ET

endstream
endobj
5 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
xref
0 6
0000000000 65535 f
0000000009 00000 n
0000000058 00000 n
0000000115 00000 n
0000000241 00000 n
0000000334 00000 n
trailer
<< /Root 1 0 R /Size 6 >>
startxref
404
%%EOF
PDF;
}

/**
 * @return array{width:int, height:int}
 */
function pngDimensions(string $imageContents): array
{
    $size = getimagesizefromstring($imageContents);

    if ($size === false) {
        throw new RuntimeException('Failed to determine rendered image dimensions.');
    }

    return [
        'width' => $size[0],
        'height' => $size[1],
    ];
}
