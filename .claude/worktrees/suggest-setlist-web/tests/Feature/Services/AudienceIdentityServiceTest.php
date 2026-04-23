<?php

declare(strict_types=1);

use App\Models\AudienceProfile;
use App\Models\Project;
use App\Services\AudienceIdentityService;

it('does not auto-generate display name for profiles with blank display_name', function () {
    $project = Project::factory()->create();
    $visitorToken = 'test-token-abc-123';

    // Pre-create a profile with an empty display_name
    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'visitor_token' => strtolower($visitorToken),
        'display_name' => '',
        'last_seen_at' => now()->subDay(),
    ]);

    $service = new AudienceIdentityService;
    $result = $service->resolveProfile($project, $visitorToken, '127.0.0.1');

    // display_name should remain empty — only real names from Stripe are saved
    expect($result->id)->toBe($profile->id)
        ->and($result->display_name)->toBe('');
});
