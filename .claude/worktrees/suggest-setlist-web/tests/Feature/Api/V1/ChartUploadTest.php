<?php

declare(strict_types=1);

use App\Enums\ChartTheme;
use App\Jobs\RenderChartPages;
use App\Models\Chart;
use App\Models\ChartRender;
use App\Models\Project;
use App\Models\Song;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('filesystems.chart', 'r2');
    Storage::fake('r2');

    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    Sanctum::actingAs($this->owner);
});

it('returns 200 and keeps the existing chart when the uploaded PDF is identical', function () {
    Queue::fake();

    $song = Song::factory()->create([
        'title' => 'Wonderwall',
        'artist' => 'Oasis',
    ]);
    $incomingFile = chartUploadApiPdfUpload('wonderwall.pdf');
    $incomingContents = file_get_contents($incomingFile->getRealPath());
    expect(is_string($incomingContents))->toBeTrue();

    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'has_renders' => true,
        'page_count' => 2,
        'source_sha256' => hash('sha256', $incomingContents),
    ]);
    Storage::disk('r2')->put($chart->storage_path_pdf, $incomingContents);

    $originalFilename = $chart->original_filename;
    $originalPath = $chart->storage_path_pdf;
    $originalUpdatedAt = $chart->updated_at?->toIso8601String();

    $response = $this->postJson('/api/v1/me/charts', [
        'file' => $incomingFile,
        'song_id' => $song->id,
        'project_id' => $this->project->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Chart already matches the uploaded PDF.')
        ->assertJsonPath('chart.id', $chart->id)
        ->assertJsonPath('chart.updated_at', $originalUpdatedAt);

    expect(Chart::query()->count())->toBe(1);

    $chart->refresh();
    expect($chart->storage_path_pdf)->toBe($originalPath);
    expect($chart->original_filename)->toBe($originalFilename);
    expect($chart->has_renders)->toBeTrue();
    expect($chart->page_count)->toBe(2);

    Queue::assertNotPushed(RenderChartPages::class);
});

it('replaces the existing chart in place and resets render state', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick extension is required for chart upload replacement tests.');
    }

    if (! chartUploadApiHasGhostscriptBinary()) {
        $this->markTestSkipped('Ghostscript (gs) is required for chart upload replacement tests.');
    }

    Queue::fake();

    $song = Song::factory()->create([
        'title' => 'Wonderwall',
        'artist' => 'Oasis',
    ]);
    $chart = Chart::factory()->create([
        'owner_user_id' => $this->owner->id,
        'project_id' => $this->project->id,
        'song_id' => $song->id,
        'has_renders' => true,
        'page_count' => 2,
        'source_sha256' => hash('sha256', 'old-pdf'),
        'storage_path_pdf' => "charts/{$this->owner->id}/legacy-source.pdf",
        'original_filename' => 'legacy.pdf',
    ]);

    $renderPath = "charts/{$this->owner->id}/{$chart->id}/renders/light/page-1.png";
    ChartRender::factory()->create([
        'chart_id' => $chart->id,
        'page_number' => 1,
        'theme' => ChartTheme::Light,
        'storage_path_image' => $renderPath,
    ]);

    Storage::disk('r2')->put($chart->storage_path_pdf, 'old-pdf');
    Storage::disk('r2')->put($renderPath, 'stale-render');

    $incomingFile = chartUploadApiPdfUpload('replacement.pdf');
    $incomingContents = file_get_contents($incomingFile->getRealPath());
    expect(is_string($incomingContents))->toBeTrue();

    $response = $this->postJson('/api/v1/me/charts', [
        'file' => $incomingFile,
        'song_id' => $song->id,
        'project_id' => $this->project->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Chart replaced successfully.')
        ->assertJsonPath('chart.id', $chart->id)
        ->assertJsonPath('chart.page_count', 0)
        ->assertJsonPath('chart.has_renders', false);

    $chart->refresh();
    expect($chart->storage_path_pdf)->toBe("charts/{$this->owner->id}/{$chart->id}/source.pdf");
    expect($chart->source_sha256)->toBe(hash('sha256', $incomingContents));
    expect($chart->original_filename)->toBe('replacement.pdf');
    expect($chart->has_renders)->toBeFalse();
    expect($chart->page_count)->toBe(0);
    expect($chart->renders()->count())->toBe(0);

    Storage::disk('r2')->assertExists($chart->storage_path_pdf);
    Storage::disk('r2')->assertMissing("charts/{$this->owner->id}/legacy-source.pdf");
    Storage::disk('r2')->assertMissing($renderPath);

    Queue::assertPushed(RenderChartPages::class, 1);

    $statusResponse = $this->getJson("/api/v1/me/charts/{$chart->id}/render-status");
    $statusResponse->assertOk()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('pending', true)
        ->assertJsonPath('ready', false)
        ->assertJsonPath('failure_reason', null);
});

it('rejects chart uploads with a non-pdf mimetype', function () {
    Queue::fake();

    $song = Song::factory()->create();
    $notAPdf = UploadedFile::fake()->create('fake.txt', 100, 'text/plain');

    $this->postJson('/api/v1/me/charts', [
        'file' => $notAPdf,
        'song_id' => $song->id,
        'project_id' => $this->project->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    expect(Chart::query()->count())->toBe(0);
});

function chartUploadApiHasGhostscriptBinary(): bool
{
    foreach (['/opt/homebrew/bin/gs', '/usr/local/bin/gs', '/usr/bin/gs'] as $binaryPath) {
        if (is_executable($binaryPath)) {
            return true;
        }
    }

    return trim((string) shell_exec('command -v gs 2>/dev/null')) !== '';
}

function chartUploadApiPdfUpload(string $name = 'chart.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, chartUploadApiPdfContent());
}

function chartUploadApiPdfContent(): string
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
