<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\ProjectSongAudioFile;
use App\Models\Song;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('filesystems.audio', 'audio');
    Storage::fake('audio');

    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $this->song = Song::factory()->create([
        'title' => 'Test Song',
        'artist' => 'Test Artist',
    ]);
    $this->projectSong = ProjectSong::factory()->create([
        'project_id' => $this->project->id,
        'song_id' => $this->song->id,
    ]);

    Sanctum::actingAs($this->owner);
});

function audioFileBaseUrl(): string
{
    return '/api/v1/me/projects/'
        .test()->project->id
        .'/repertoire/'
        .test()->projectSong->id
        .'/audio-files';
}

function createAudioFileRecord(array $overrides = []): ProjectSongAudioFile
{
    $defaults = [
        'project_song_id' => test()->projectSong->id,
        'project_id' => test()->project->id,
        'owner_user_id' => test()->owner->id,
        'storage_disk' => 'audio',
        'storage_path' => 'audio/'.test()->project->id.'/'.test()->projectSong->id.'/placeholder.mp3',
        'original_filename' => 'song.mp3',
        'label' => null,
        'file_size_bytes' => 1024,
        'source_sha256' => hash('sha256', 'audio-content-'.random_int(1, 999999)),
        'sort_order' => 0,
    ];

    $audioFile = ProjectSongAudioFile::query()->create(array_merge($defaults, $overrides));

    Storage::disk('audio')->put($audioFile->storage_path, 'fake-mp3-content');

    return $audioFile;
}

function fakeMp3(string $name = 'track.mp3', string $content = 'fake-mp3-bytes'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

// ---------------------------------------------------------------------------
// 1. List audio files for a project song
// ---------------------------------------------------------------------------
it('lists audio files for a project song', function () {
    $fileA = createAudioFileRecord(['sort_order' => 0, 'original_filename' => 'a.mp3']);
    $fileB = createAudioFileRecord(['sort_order' => 1, 'original_filename' => 'b.mp3']);

    $response = $this->getJson(audioFileBaseUrl());

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $fileA->id)
        ->assertJsonPath('data.1.id', $fileB->id);
});

// ---------------------------------------------------------------------------
// 2. Upload an MP3 file (201 response)
// ---------------------------------------------------------------------------
it('uploads an mp3 file and returns 201', function () {
    $file = fakeMp3('my-track.mp3');

    $response = $this->postJson(audioFileBaseUrl(), [
        'file' => $file,
        'label' => 'Lead sheet',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Audio file uploaded successfully.')
        ->assertJsonPath('data.original_filename', 'my-track.mp3')
        ->assertJsonPath('data.label', 'Lead sheet')
        ->assertJsonPath('data.sort_order', 0);

    $this->assertDatabaseHas('project_song_audio_files', [
        'project_song_id' => $this->projectSong->id,
        'original_filename' => 'my-track.mp3',
        'label' => 'Lead sheet',
    ]);
});

// ---------------------------------------------------------------------------
// 3. Reject non-MP3 file (422)
// ---------------------------------------------------------------------------
it('rejects a non-mp3 file with 422', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->postJson(audioFileBaseUrl(), [
        'file' => $file,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

// ---------------------------------------------------------------------------
// 4. Max 3 files enforcement (422 on 4th upload)
// ---------------------------------------------------------------------------
it('rejects a 4th audio file upload with 422', function () {
    createAudioFileRecord(['sort_order' => 0]);
    createAudioFileRecord(['sort_order' => 1]);
    createAudioFileRecord(['sort_order' => 2]);

    $response = $this->postJson(audioFileBaseUrl(), [
        'file' => fakeMp3('fourth.mp3'),
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Maximum of 3 audio files per song.');
});

// ---------------------------------------------------------------------------
// 5. SHA-256 dedup — duplicate returns existing record (201, no new row)
// ---------------------------------------------------------------------------
it('returns the existing audio file when uploading a duplicate', function () {
    $content = 'identical-mp3-content';
    $sha = hash('sha256', $content);

    $existing = createAudioFileRecord([
        'source_sha256' => $sha,
        'sort_order' => 0,
    ]);

    $response = $this->postJson(audioFileBaseUrl(), [
        'file' => fakeMp3('duplicate.mp3', $content),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.id', $existing->id);

    expect(
        ProjectSongAudioFile::query()
            ->where('project_song_id', $this->projectSong->id)
            ->count()
    )->toBe(1);
});

// ---------------------------------------------------------------------------
// 6. Get signed URL (200, returns url field)
// ---------------------------------------------------------------------------
it('returns a signed url for an audio file', function () {
    $audioFile = createAudioFileRecord();

    $response = $this->getJson(audioFileBaseUrl()."/{$audioFile->id}/signed-url");

    $response->assertOk()
        ->assertJsonStructure(['url']);

    expect($response->json('url'))->toBeString()->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// 7. Replace an audio file with new MP3 (200)
// ---------------------------------------------------------------------------
it('replaces an audio file with a new mp3', function () {
    $audioFile = createAudioFileRecord([
        'original_filename' => 'old.mp3',
        'source_sha256' => hash('sha256', 'old-content'),
    ]);

    $newFile = fakeMp3('new-version.mp3', 'brand-new-content');

    $response = $this->postJson(audioFileBaseUrl()."/{$audioFile->id}/replace", [
        'file' => $newFile,
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Audio file replaced successfully.')
        ->assertJsonPath('data.id', $audioFile->id)
        ->assertJsonPath('data.original_filename', 'new-version.mp3');

    $audioFile->refresh();
    expect($audioFile->source_sha256)->toBe(hash('sha256', 'brand-new-content'));
});

// ---------------------------------------------------------------------------
// 8. Update label (set and clear with null)
// ---------------------------------------------------------------------------
it('updates the label on an audio file', function () {
    $audioFile = createAudioFileRecord(['label' => null]);

    $response = $this->putJson(audioFileBaseUrl()."/{$audioFile->id}", [
        'label' => 'Backing track',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.label', 'Backing track');

    $audioFile->refresh();
    expect($audioFile->label)->toBe('Backing track');
});

it('clears the label when null is sent explicitly', function () {
    $audioFile = createAudioFileRecord(['label' => 'Old label']);

    $response = $this->putJson(audioFileBaseUrl()."/{$audioFile->id}", [
        'label' => null,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.label', null);

    $audioFile->refresh();
    expect($audioFile->label)->toBeNull();
});

// ---------------------------------------------------------------------------
// 9. Delete audio file with sort_order reindex
// ---------------------------------------------------------------------------
it('deletes an audio file and reindexes sort_order', function () {
    $fileA = createAudioFileRecord(['sort_order' => 0, 'original_filename' => 'a.mp3']);
    $fileB = createAudioFileRecord(['sort_order' => 1, 'original_filename' => 'b.mp3']);
    $fileC = createAudioFileRecord(['sort_order' => 2, 'original_filename' => 'c.mp3']);

    $response = $this->deleteJson(audioFileBaseUrl()."/{$fileB->id}");

    $response->assertOk()
        ->assertJsonPath('message', 'Audio file deleted successfully.');

    $this->assertDatabaseMissing('project_song_audio_files', ['id' => $fileB->id]);

    $fileA->refresh();
    $fileC->refresh();

    expect($fileA->sort_order)->toBe(0);
    expect($fileC->sort_order)->toBe(1);
});

// ---------------------------------------------------------------------------
// 10. Access control — non-member cannot access
// ---------------------------------------------------------------------------
it('returns 404 when a non-member tries to list audio files', function () {
    $outsider = User::factory()->create();
    Sanctum::actingAs($outsider);

    $response = $this->getJson(audioFileBaseUrl());

    $response->assertNotFound();
});

it('returns 404 when a non-member tries to upload an audio file', function () {
    $outsider = User::factory()->create();
    Sanctum::actingAs($outsider);

    $response = $this->postJson(audioFileBaseUrl(), [
        'file' => fakeMp3(),
    ]);

    $response->assertNotFound();
});

it('returns 404 when a non-member tries to delete an audio file', function () {
    $audioFile = createAudioFileRecord();

    $outsider = User::factory()->create();
    Sanctum::actingAs($outsider);

    $response = $this->deleteJson(audioFileBaseUrl()."/{$audioFile->id}");

    $response->assertNotFound();
});
