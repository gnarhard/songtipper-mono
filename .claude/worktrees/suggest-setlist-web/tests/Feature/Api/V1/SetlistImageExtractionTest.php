<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);

    config()->set('services.ai.provider', 'anthropic');
    config()->set('services.anthropic.api_key', 'test-key');
    config()->set('services.anthropic.model', 'claude-sonnet-4-6');
    config()->set('services.anthropic.timeout_seconds', 5);
});

it('extracts song titles from an uploaded setlist image', function () {
    Sanctum::actingAs($this->owner);

    // With prefill '{"songs":[', Claude returns the continuation (without opening {"songs":[)
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => '{"title":"Bohemian Rhapsody","artist":"Queen"},{"title":"Hotel California","artist":"Eagles"},{"title":"Wonderwall","artist":"Oasis"}]}',
            ]],
        ], 200),
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/extract-songs-from-image",
        ['image' => UploadedFile::fake()->image('setlist.jpg', 800, 600)],
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.songs.0.title', 'Bohemian Rhapsody')
        ->assertJsonPath('data.songs.1.title', 'Hotel California')
        ->assertJsonPath('data.songs.2.title', 'Wonderwall')
        ->assertJsonPath('data.songs.0.set_label', null);
});

it('returns 422 when AI extraction fails', function () {
    Sanctum::actingAs($this->owner);

    Http::fake([
        'api.anthropic.com/*' => Http::response('Internal Server Error', 500),
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/extract-songs-from-image",
        ['image' => UploadedFile::fake()->image('setlist.jpg', 800, 600)],
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

it('returns 422 when AI returns empty songs list', function () {
    Sanctum::actingAs($this->owner);

    // With prefill '{"songs":[', Claude returns the continuation
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => ']}',
            ]],
        ], 200),
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/extract-songs-from-image",
        ['image' => UploadedFile::fake()->image('setlist.jpg', 800, 600)],
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

it('rejects non-image files', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/extract-songs-from-image",
        ['image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

it('rejects files exceeding max size', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/extract-songs-from-image",
        ['image' => UploadedFile::fake()->image('huge.jpg')->size(11000)],
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

it('rejects requests without an image', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/extract-songs-from-image",
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

it('returns 404 for unauthorized project', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    Http::fake();

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/extract-songs-from-image",
        ['image' => UploadedFile::fake()->image('setlist.jpg', 800, 600)],
    );

    $response->assertNotFound();
});

it('handles AI response with markdown code fences', function () {
    Sanctum::actingAs($this->owner);

    // Even with prefill, Claude might still wrap in code fences
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => "```json\n{\"title\":\"Sweet Caroline\",\"artist\":\"Neil Diamond\"},{\"title\":\"Piano Man\",\"artist\":\"Billy Joel\"}]}\n```",
            ]],
        ], 200),
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/extract-songs-from-image",
        ['image' => UploadedFile::fake()->image('setlist.jpg', 800, 600)],
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.songs.0.title', 'Sweet Caroline')
        ->assertJsonPath('data.songs.1.title', 'Piano Man');
});

it('returns set labels when AI detects set groupings', function () {
    Sanctum::actingAs($this->owner);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => '{"title":"Bohemian Rhapsody","artist":"Queen","set":"Set 1"},{"title":"Hotel California","artist":"Eagles","set":"Set 2"}]}',
            ]],
        ], 200),
    ]);

    $response = $this->postJson(
        "/api/v1/me/projects/{$this->project->id}/setlists/extract-songs-from-image",
        ['image' => UploadedFile::fake()->image('setlist.jpg', 800, 600)],
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.songs.0.title', 'Bohemian Rhapsody')
        ->assertJsonPath('data.songs.0.set_label', 'Set 1')
        ->assertJsonPath('data.songs.1.title', 'Hotel California')
        ->assertJsonPath('data.songs.1.set_label', 'Set 2');
});
