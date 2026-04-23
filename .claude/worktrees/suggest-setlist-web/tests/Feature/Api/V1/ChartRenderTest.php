<?php

declare(strict_types=1);

use App\Models\Chart;
use App\Models\Project;
use App\Models\Song;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick extension is required for chart render tests.');
    }

    if (! chartRenderApiHasGhostscriptBinary()) {
        $this->markTestSkipped('Ghostscript (gs) is required for chart render tests.');
    }

    config()->set('queue.default', 'sync');
    config()->set('filesystems.chart', 'r2');
    Storage::fake('r2');

    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    Sanctum::actingAs($this->owner);
});

it('renders chart pages when uploading a chart', function () {
    $song = Song::factory()->create([
        'title' => 'Render Test Song',
        'artist' => 'Render Test Artist',
    ]);

    $response = $this->postJson('/api/v1/me/charts', [
        'file' => chartRenderApiPdfUpload('upload-test.pdf'),
        'song_id' => $song->id,
        'project_id' => $this->project->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Chart uploaded successfully.');

    $chart = Chart::query()->findOrFail($response->json('chart.id'));
    $chart->refresh();

    expect($response->json('chart.updated_at'))->not->toBeNull();

    expect($chart->has_renders)->toBeTrue();
    expect($chart->page_count)->toBe(1);

    $renders = $chart->renders()->orderBy('theme')->get();
    expect($renders)->toHaveCount(2);
    expect($renders->pluck('theme')->map(fn ($theme) => $theme->value)->all())->toBe(['dark', 'light']);
    expect($renders->pluck('page_number')->unique()->values()->all())->toBe([1]);

    Storage::disk(config('filesystems.chart'))->assertExists($chart->storage_path_pdf);

    foreach ($renders as $render) {
        Storage::disk(config('filesystems.chart'))->assertExists($render->storage_path_image);
    }
});

it('replaces existing render records when re-rendering a chart', function () {
    $song = Song::factory()->create([
        'title' => 'Manual Render Song',
        'artist' => 'Manual Render Artist',
    ]);

    $chart = Chart::create([
        'owner_user_id' => $this->owner->id,
        'song_id' => $song->id,
        'project_id' => $this->project->id,
        'storage_disk' => config('filesystems.chart'),
        'storage_path_pdf' => "charts/{$this->owner->id}/manual-render/source.pdf",
        'original_filename' => 'manual-render.pdf',
        'has_renders' => false,
        'page_count' => 0,
    ]);

    Storage::disk(config('filesystems.chart'))->put($chart->storage_path_pdf, chartRenderApiPdfContent());

    $staleStoragePath = "charts/{$this->owner->id}/{$chart->id}/renders/light/stale-page-1.png";
    $chart->renders()->create([
        'page_number' => 1,
        'theme' => 'light',
        'storage_path_image' => $staleStoragePath,
    ]);

    $response = $this->postJson("/api/v1/me/charts/{$chart->id}/render");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Chart render job dispatched.');

    $chart->refresh();
    expect($chart->has_renders)->toBeTrue();
    expect($chart->page_count)->toBe(1);

    $renders = $chart->renders()->orderBy('theme')->get();
    expect($renders)->toHaveCount(2);
    expect($chart->renders()->where('storage_path_image', $staleStoragePath)->exists())->toBeFalse();

    $expectedLightPath = "charts/{$this->owner->id}/{$chart->id}/renders/light/page-1.png";
    $expectedDarkPath = "charts/{$this->owner->id}/{$chart->id}/renders/dark/page-1.png";

    expect($renders->pluck('storage_path_image')->all())
        ->toBe([$expectedDarkPath, $expectedLightPath]);

    Storage::disk(config('filesystems.chart'))->assertExists($expectedLightPath);
    Storage::disk(config('filesystems.chart'))->assertExists($expectedDarkPath);
});

function chartRenderApiHasGhostscriptBinary(): bool
{
    foreach (['/opt/homebrew/bin/gs', '/usr/local/bin/gs', '/usr/bin/gs'] as $binaryPath) {
        if (is_executable($binaryPath)) {
            return true;
        }
    }

    return trim((string) shell_exec('command -v gs 2>/dev/null')) !== '';
}

function chartRenderApiPdfUpload(string $name = 'chart.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, chartRenderApiPdfContent());
}

function chartRenderApiPdfContent(): string
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
