<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use App\Services\AccountUsageService;
use App\Services\SongMetadataLookupService;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
});

it('enriches songs and returns metadata', function () {
    Sanctum::actingAs($this->owner);

    $this->mock(AccountUsageService::class, function (MockInterface $mock) {
        $mock->shouldIgnoreMissing();
        $mock->shouldReceive('aiInteractiveLimitResponse')->andReturn(null);
        $mock->shouldReceive('recordAiOperation');
    });

    $this->mock(SongMetadataLookupService::class, function (MockInterface $mock) {
        $mock->shouldReceive('lookup')
            ->once()
            ->andReturn([
                'source' => 'ai',
                'provider_called' => true,
                'provider_name' => 'claude',
                'metadata' => [
                    'energy_level' => 'high',
                    'era' => '80s',
                    'genre' => 'Rock',
                ],
            ]);
    });

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-enrich", [
        'songs' => [
            ['title' => 'Bohemian Rhapsody', 'artist' => 'Queen'],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.ai_calls_used', 1)
        ->assertJsonPath('data.songs.0.title', 'Bohemian Rhapsody')
        ->assertJsonPath('data.songs.0.source', 'ai');
});

it('returns 429 when AI limit is reached', function () {
    Sanctum::actingAs($this->owner);

    $this->mock(AccountUsageService::class, function (MockInterface $mock) {
        $mock->shouldIgnoreMissing();
        $mock->shouldReceive('aiInteractiveLimitResponse')->andReturn([
            'body' => ['message' => 'AI limit reached'],
            'status' => 429,
        ]);
    });

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-enrich", [
        'songs' => [
            ['title' => 'Test', 'artist' => 'Artist'],
        ],
    ]);

    $response->assertStatus(429);
});

it('returns 404 for projects the user cannot access', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-enrich", [
        'songs' => [
            ['title' => 'Test', 'artist' => 'Artist'],
        ],
    ]);

    $response->assertNotFound();
});

it('requires authentication', function () {
    $this->postJson("/api/v1/me/projects/{$this->project->id}/repertoire/bulk-enrich", [
        'songs' => [['title' => 'Test', 'artist' => 'Artist']],
    ])->assertUnauthorized();
});
